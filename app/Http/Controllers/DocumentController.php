<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Team;
use App\Services\ActivityLogger;
use App\Support\Tenancy\TenantRule;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Thư viện tài liệu (bước 4.5).
 *  - Tệp lưu ở storage/app/private với tên ngẫu nhiên; loại tệp kiểm tra theo NỘI DUNG thật (không tin đuôi tệp / MIME trình duyệt gửi).
 *  - Xem / tải chỉ qua controller sau khi kiểm tra phạm vi xem; mỗi lượt tải được ghi nhật ký.
 *  - Phiên bản bất biến: tải bản mới, bản cũ vẫn giữ để đối chiếu.
 */
class DocumentController extends Controller
{
    private function filtered(Request $request)
    {
        return Document::visibleTo($request->user())->with(['latestVersion', 'owner:id,name', 'team:id,name', 'related'])->withCount('versions')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $this->like($request->q);
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('description', 'like', $term)
                    ->orWhereHas('versions', fn ($v) => $v->where('original_name', 'like', $term)));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('visibility'), fn ($q) => $q->where('visibility', $request->visibility))
            ->when($request->filled('related_type') && $request->filled('related_id'), fn ($q) => $q->where('related_type', $request->related_type)->where('related_id', $request->integer('related_id')))
            ->when($request->input('mine') === '1', fn ($q) => $q->where('created_by', $request->user()->id));
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->can('documents.view'), 403);
        $request->validate([
            'category' => ['nullable', Rule::in(array_keys(Document::CATEGORIES))],
            'visibility' => ['nullable', Rule::in(array_keys(Document::VISIBILITIES))],
            'related_type' => ['nullable', Rule::in(array_keys(Document::relatedTypes()))],
        ]);
        $documents = $this->filtered($request)->latest('updated_at')->paginate($this->perPage($request))->withQueryString();

        return view('documents.index', compact('documents'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->can('documents.create'), 403);
        $document = new Document(['category' => 'other', 'visibility' => 'organization',
            'related_type' => $request->input('related_type'), 'related_id' => $request->integer('related_id') ?: null]);

        return view('documents.form', $this->formData($request, $document));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('documents.create'), 403);
        $data = $this->validated($request);
        $request->validate($this->fileRules(), [], ['file' => __('Tệp tài liệu')]);

        $document = DB::transaction(function () use ($data, $request) {
            $document = Document::create($data + ['created_by' => $request->user()->id]);
            $this->storeVersion($document, $request->file('file'), $request->user()->id, null);

            return $document;
        });

        return redirect()->route('documents.show', $document)->with('success', __('Đã tải lên tài liệu :title.', ['title' => $document->title]));
    }

    public function show(Request $request, Document $document)
    {
        abort_unless($document->viewableBy($request->user()), 404);   // tài liệu không được xem: coi như không tồn tại
        $document->load(['versions.uploader:id,name', 'owner:id,name', 'team:id,name', 'related']);
        $downloads = \App\Models\ActivityLog::where('subject_type', 'document')->where('subject_id', $document->id)->where('action', 'downloaded')
            ->with('user:id,name')->latest('created_at')->limit(10)->get();

        return view('documents.show', compact('document', 'downloads'));
    }

    public function edit(Request $request, Document $document)
    {
        abort_unless($document->viewableBy($request->user()), 404);
        abort_unless($document->manageableBy($request->user()), 403);

        return view('documents.form', $this->formData($request, $document));
    }

    public function update(Request $request, Document $document)
    {
        abort_unless($document->viewableBy($request->user()), 404);
        abort_unless($document->manageableBy($request->user()), 403);
        $document->update($this->validated($request, $document));

        return redirect()->route('documents.show', $document)->with('success', __('Đã cập nhật tài liệu.'));
    }

    public function destroy(Request $request, Document $document)
    {
        abort_unless($document->viewableBy($request->user()), 404);
        abort_unless($document->manageableBy($request->user()), 403);
        $document->delete();   // xóa mềm: tệp vẫn giữ để khôi phục từ Thùng rác

        return redirect()->route('documents.index')->with('success', __('Đã xóa tài liệu.').' '.__('Có thể khôi phục trong Thùng rác.'));
    }

    public function addVersion(Request $request, Document $document)
    {
        abort_unless($document->viewableBy($request->user()), 404);
        abort_unless($document->manageableBy($request->user()), 403);
        $request->validate($this->fileRules() + ['note' => ['nullable', 'string', 'max:500']], [], ['file' => __('Tệp tài liệu'), 'note' => __('Ghi chú phiên bản')]);
        DB::transaction(function () use ($document, $request) {
            $this->storeVersion($document, $request->file('file'), $request->user()->id, $request->input('note'));
            $document->touch();
        });

        return back()->with('success', __('Đã tải lên phiên bản mới.'));
    }

    public function download(Request $request, Document $document, ?DocumentVersion $version = null)
    {
        $version = $this->resolveVersion($request, $document, $version);
        ActivityLogger::log('downloaded', $document, 'Tải tài liệu "'.$document->title.'" (phiên bản '.$version->version.')');

        return Storage::disk('local')->download($version->path, $version->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    /** Xem trực tiếp: chỉ PDF / ảnh (loại xác định từ nội dung khi tải lên); loại khác phải tải về. */
    public function preview(Request $request, Document $document, ?DocumentVersion $version = null)
    {
        $version = $this->resolveVersion($request, $document, $version);
        abort_unless($version->isPreviewable(), 415, __('Loại tệp này không xem trực tiếp được, hãy tải về.'));
        ActivityLogger::log('downloaded', $document, 'Xem tài liệu "'.$document->title.'" (phiên bản '.$version->version.')');

        return Storage::disk('local')->response($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type,
            'Content-Disposition' => 'inline; filename="'.Str::ascii($version->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function resolveVersion(Request $request, Document $document, ?DocumentVersion $version): DocumentVersion
    {
        abort_unless($document->viewableBy($request->user()), 404);
        $version = $version?->exists ? $version : $document->latestVersion;
        abort_unless($version && $version->document_id === $document->id, 404);
        abort_unless(Storage::disk('local')->exists($version->path), 404, __('Tệp không còn tồn tại.'));

        return $version;
    }

    private function storeVersion(Document $document, UploadedFile $file, int $userId, ?string $note): DocumentVersion
    {
        $extension = $file->guessExtension() ?: 'bin';   // theo nội dung tệp
        $path = $file->storeAs('documents/'.$document->organization_id.'/'.date('Y/m'), Str::uuid().'.'.$extension, 'local');

        return $document->versions()->create([
            'version' => (int) $document->versions()->max('version') + 1,
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'mime_type' => self::realMime($file),   // loại thật theo nội dung — quyết định có cho xem trước hay không
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'note' => $note,
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * Hai lớp kiểm tra loại tệp: "mimes" của Laravel, và tự đọc nội dung bằng finfo — loại thật phải thuộc danh sách cho phép
     * (tệp đổi đuôi giả mạo, VD mã PHP đặt tên .pdf, bị từ chối).
     */
    private function fileRules(): array
    {
        return ['file' => ['required', 'file', 'mimes:'.config('crm.document_mimes'), 'max:'.config('crm.document_max_kb'),
            function (string $attribute, $file, \Closure $fail) {
                if (! $file instanceof UploadedFile || ! in_array(self::realMime($file), self::allowedMimes(), true)) {
                    $fail(__('Nội dung tệp không khớp loại tệp cho phép.'));
                }
            }]];
    }

    /** Loại MIME đọc từ nội dung tệp (không tin tên tệp / MIME trình duyệt gửi). */
    private static function realMime(UploadedFile $file): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
    }

    /** Danh sách MIME tương ứng các đuôi tệp được phép (config crm.document_mimes). */
    private static function allowedMimes(): array
    {
        $types = new \Symfony\Component\Mime\MimeTypes;
        $mimes = [];
        foreach (explode(',', (string) config('crm.document_mimes')) as $ext) {
            $mimes = array_merge($mimes, $types->getMimeTypes(trim($ext)));
        }

        // Tệp Office (docx/xlsx/pptx) có cấu trúc ZIP: finfo có thể trả về application/zip cho tệp Office hợp lệ
        return array_values(array_unique(array_merge($mimes, ['application/zip', 'text/plain', 'text/csv'])));
    }

    private function validated(Request $request, ?Document $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'visibility' => ['required', Rule::in(array_keys(Document::VISIBILITIES))],
            'team_id' => ['nullable', 'required_if:visibility,team', 'integer', TenantRule::exists('teams')],
            'related_type' => ['nullable', Rule::in(array_keys(Document::relatedTypes()))],
            'related_id' => ['nullable', 'required_with:related_type', 'integer'],
        ], [], ['title' => __('Tên tài liệu'), 'team_id' => __('Nhóm'), 'related_id' => __('Đối tượng liên quan')]);

        // Chia sẻ theo nhóm: người tải lên phải thuộc nhóm (hoặc là quản trị tài liệu)
        if ($data['visibility'] === 'team') {
            $isMember = DB::table('team_members')->where('team_id', $data['team_id'])->where('user_id', $request->user()->id)->exists();
            if (! $isMember && ! $request->user()->can('documents.manage')) {
                throw ValidationException::withMessages(['team_id' => __('Bạn chỉ chia sẻ được cho nhóm mình tham gia.')]);
            }
        } else {
            $data['team_id'] = null;
        }

        // Đối tượng liên kết phải là bản ghi người dùng được xem (giữ nguyên liên kết cũ khi không đổi)
        if (! empty($data['related_type'])) {
            $unchanged = $existing && $existing->related_type === $data['related_type'] && (int) $existing->related_id === (int) $data['related_id'];
            $class = Document::relatedTypes()[$data['related_type']][0];
            $related = $class::find($data['related_id']);
            if (! $unchanged && (! $related || ! $request->user()->can('view', $related))) {
                throw ValidationException::withMessages(['related_id' => __('Đối tượng liên quan không hợp lệ.')]);
            }
        } else {
            $data['related_type'] = null;
            $data['related_id'] = null;
        }

        return $data;
    }

    private function formData(Request $request, Document $document): array
    {
        $user = $request->user();
        $teams = $user->can('documents.manage') ? Team::orderBy('name')->pluck('name', 'id')->all() : $user->teams()->orderBy('name')->pluck('teams.name', 'teams.id')->all();

        return ['document' => $document, 'teams' => $teams, 'relatedOptions' => [
            'customer' => \App\Models\Customer::visibleTo($user)->orderBy('name')->limit(500)->pluck('name', 'id')->all(),
            'opportunity' => \App\Models\Opportunity::visibleTo($user)->orderBy('title')->limit(500)->pluck('title', 'id')->all(),
            'project' => \App\Models\Project::visibleTo($user)->orderBy('name')->limit(500)->pluck('name', 'id')->all(),
        ]];
    }
}
