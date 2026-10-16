<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Product;
use App\Services\Import\ContactImport;
use App\Services\Import\LeadImport;
use App\Services\Import\ProductImport;
use App\Services\Import\TableImport;
use Illuminate\Http\Request;

/** Nhập từ CSV / Excel cho lead, người liên hệ, sản phẩm (quyền: được tạo bản ghi module đó). */
class ImportController extends Controller
{
    /** @return array<string, array{importer: class-string<TableImport>, model: class-string, title: string, crumb: string, file: string, note: string}> */
    public static function modules(): array
    {
        return [
            'leads' => ['importer' => LeadImport::class, 'model' => Lead::class, 'title' => 'Nhập lead từ CSV / Excel', 'crumb' => 'Leads',
                'file' => 'mau_nhap_lead.csv', 'note' => 'Dòng trùng email/điện thoại với lead hoặc khách hàng đã có sẽ bị bỏ qua. Lead nhập vào sẽ do bạn phụ trách.'],
            'contacts' => ['importer' => ContactImport::class, 'model' => Contact::class, 'title' => 'Nhập người liên hệ từ CSV / Excel', 'crumb' => 'Người liên hệ',
                'file' => 'mau_nhap_nguoi_lien_he.csv', 'note' => 'Dòng trùng email với người liên hệ đã có sẽ bị bỏ qua.'],
            'products' => ['importer' => ProductImport::class, 'model' => Product::class, 'title' => 'Nhập sản phẩm từ CSV / Excel', 'crumb' => 'Sản phẩm',
                'file' => 'mau_nhap_san_pham.csv', 'note' => 'Mã SKU đã có (kể cả sản phẩm trong thùng rác) sẽ bị bỏ qua — không ghi đè sản phẩm cũ.'],
        ];
    }

    private function module(string $module): array
    {
        $def = self::modules()[$module] ?? abort(404);
        $this->authorize('create', $def['model']);

        return $def + ['key' => $module, 'service' => app($def['importer'])];
    }

    public function form(string $module)
    {
        $m = $this->module($module);

        return view('imports.form', ['m' => $m, 'columns' => $m['service']->columns(),
            'currentOrganization' => app(\App\Support\Tenancy\CurrentOrganization::class)->get()]);
    }

    public function template(string $module)
    {
        $m = $this->module($module);

        return response($m['service']->templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$m['file'].'"',
        ]);
    }

    public function store(Request $request, string $module)
    {
        $m = $this->module($module);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120']], [], ['file' => __('Tệp CSV / Excel')]);

        $result = $m['service']->import($request->file('file'), $request->user());

        return redirect()->route($module.'.import')->with('import_result', $result)
            ->with($result['created'] > 0 ? 'success' : 'warning', __('Đã nhập :created dòng, bỏ qua :skipped dòng.', ['created' => $result['created'], 'skipped' => $result['skipped']]));
    }
}
