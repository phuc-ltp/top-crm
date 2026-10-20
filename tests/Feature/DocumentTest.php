<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bước 4.5: thư viện tài liệu — tải lên an toàn, phạm vi xem, phiên bản, xem trước, tải xuống có nhật ký, cách ly tổ chức. */
class DocumentTest extends TestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Tệp THẬT trên đĩa (không phải tệp giả lập): loại tệp được dò từ nội dung như khi người dùng tải lên. */
    private function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function upload(User $user, array $data = [], ?UploadedFile $file = null)
    {
        return $this->actingAs($user)->post(route('documents.store'), $data + ['title' => 'Hợp đồng mẫu', 'category' => 'contract', 'visibility' => 'organization',
            'file' => $file ?? UploadedFile::fake()->createWithContent('hop-dong.pdf', self::PDF)]);
    }

    public function test_upload_preview_download_and_audit_log(): void
    {
        $owner = $this->userWithRole('Sales Employee');
        $this->upload($owner)->assertRedirect();
        $doc = Document::firstOrFail();
        $v = $doc->latestVersion;
        $this->assertSame([1, 'application/pdf', hash('sha256', self::PDF)], [$v->version, $v->mime_type, $v->sha256]);
        $this->assertStringStartsWith('documents/', $v->path);
        Storage::disk('local')->assertExists($v->path);
        $this->assertFalse(Storage::disk('public')->exists($v->path), 'Tệp không được nằm trong thư mục công khai');

        $colleague = $this->userWithRole('Employee');
        $this->actingAs($colleague)->get(route('documents.index'))->assertOk()->assertSee('Hợp đồng mẫu');
        $preview = $this->get(route('documents.preview', $doc))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('inline', $preview->headers->get('Content-Disposition'));
        $this->get(route('documents.download', $doc))->assertOk()->assertDownload('hop-dong.pdf');
        $this->assertSame(2, \App\Models\ActivityLog::where('subject_type', 'document')->where('action', 'downloaded')->where('user_id', $colleague->id)->count());
        $this->get(route('documents.show', $doc))->assertOk()->assertSee($colleague->name);
    }

    public function test_spoofed_and_disallowed_files_are_rejected(): void
    {
        $user = $this->userWithRole('Sales Employee');
        $this->upload($user, [], $this->realFile('bao-cao.pdf', "<?php system(\$_GET['c']); ?>"))->assertSessionHasErrors('file');
        $this->upload($user, [], $this->realFile('trang.pdf', '<html><body><script>alert(1)</script></body></html>'))->assertSessionHasErrors('file');
        $this->upload($user, [], $this->realFile('script.html', '<script>alert(1)</script>'))->assertSessionHasErrors('file');
        $this->upload($user, [], UploadedFile::fake()->create('lon.pdf', 30000, 'application/pdf'))->assertSessionHasErrors('file');
        $this->assertSame(0, Document::count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        // Tệp PDF thật được chấp nhận và lưu đúng loại theo nội dung
        $this->upload($user, ['title' => 'PDF thật'], $this->realFile('that.pdf', self::PDF))->assertSessionHasNoErrors();
        $this->assertSame('application/pdf', Document::where('title', 'PDF thật')->first()->latestVersion->mime_type);
    }

    public function test_visibility_private_team_and_organization(): void
    {
        $owner = $this->userWithRole('Sales Employee');
        $teammate = $this->userWithRole('Sales Employee');
        $outsider = $this->userWithRole('Sales Employee');
        $admin = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $team = Team::create(['name' => 'Nhóm Bắc']);
        $team->members()->sync([$owner->id, $teammate->id]);

        $this->upload($owner, ['title' => 'Tài liệu riêng', 'visibility' => 'private']);
        $this->upload($owner, ['title' => 'Tài liệu nhóm', 'visibility' => 'team', 'team_id' => $team->id]);
        [$private, $teamDoc] = [Document::where('title', 'Tài liệu riêng')->first(), Document::where('title', 'Tài liệu nhóm')->first()];

        $this->actingAs($teammate)->get(route('documents.index'))->assertSee('Tài liệu nhóm')->assertDontSee('Tài liệu riêng');
        $this->get(route('documents.download', $private))->assertNotFound();
        $this->get(route('documents.download', $teamDoc))->assertOk();

        $this->actingAs($outsider)->get(route('documents.index'))->assertDontSee('Tài liệu nhóm')->assertDontSee('Tài liệu riêng');
        $this->get(route('documents.show', $teamDoc))->assertNotFound();
        $this->get(route('documents.preview', $teamDoc))->assertNotFound();
        // Không chia sẻ được cho nhóm mình không tham gia
        $this->upload($outsider, ['title' => 'Chen nhóm', 'visibility' => 'team', 'team_id' => $team->id])->assertSessionHasErrors('team_id');

        // Quản trị tài liệu xem được tất cả
        $this->actingAs($admin)->get(route('documents.index'))->assertSee('Tài liệu riêng')->assertSee('Tài liệu nhóm');
    }

    public function test_versions_only_by_owner_or_manager_and_old_versions_kept(): void
    {
        $owner = $this->userWithRole('Sales Employee');
        $colleague = $this->userWithRole('Sales Employee');
        $this->upload($owner);
        $doc = Document::firstOrFail();
        $v1 = $doc->latestVersion;

        $v2content = self::PDF.'% phien ban 2';
        $this->actingAs($owner)->post(route('documents.versions.store', $doc), ['file' => UploadedFile::fake()->createWithContent('hop-dong-v2.pdf', $v2content), 'note' => 'Sửa điều khoản 3'])
            ->assertSessionHas('success');
        $this->assertSame(2, $doc->fresh()->latestVersion->version);
        $this->get(route('documents.download', [$doc, $v1]))->assertOk()->assertDownload('hop-dong.pdf');
        $this->get(route('documents.download', $doc))->assertDownload('hop-dong-v2.pdf');

        $this->actingAs($colleague)->post(route('documents.versions.store', $doc), ['file' => UploadedFile::fake()->createWithContent('x.pdf', self::PDF)])->assertForbidden();
        $this->put(route('documents.update', $doc), ['title' => 'Bị sửa', 'category' => 'other', 'visibility' => 'organization'])->assertForbidden();
        $this->delete(route('documents.destroy', $doc))->assertForbidden();

        $this->actingAs($owner)->delete(route('documents.destroy', $doc))->assertRedirect(route('documents.index'));
        $this->assertSoftDeleted($doc);
        Storage::disk('local')->assertExists($v1->path);   // xóa mềm: tệp còn để khôi phục
    }

    public function test_documents_are_isolated_between_organizations(): void
    {
        $owner = $this->userWithRole(\App\Support\PermissionCatalog::OWNER);
        $orgB = Organization::create(['name' => 'Công ty B', 'slug' => 'cong-ty-b']);
        [$docB, $teamB] = app(CurrentOrganization::class)->run($orgB->id, function () {
            $u = User::factory()->create(['status' => 'active']);
            $d = Document::create(['title' => 'Bí mật công ty B', 'category' => 'other', 'visibility' => 'organization', 'created_by' => $u->id]);
            $d->versions()->create(['version' => 1, 'path' => 'documents/x.pdf', 'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64), 'uploaded_by' => $u->id]);

            return [$d, Team::create(['name' => 'Nhóm B'])];
        });

        $this->actingAs($owner)->get(route('documents.index'))->assertDontSee('Bí mật công ty B');
        $this->get(route('documents.show', $docB))->assertNotFound();
        $this->get(route('documents.download', $docB))->assertNotFound();
        $this->upload($owner, ['visibility' => 'team', 'team_id' => $teamB->id])->assertSessionHasErrors('team_id');
    }
}
