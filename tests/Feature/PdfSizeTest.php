<?php

namespace Tests\Feature;

use App\Services\AssetService;
use Tests\TestCase;

/** PDF chỉ nhúng các ký tự font dùng tới (font subsetting) — file nhỏ, tiếng Việt vẫn đủ dấu. */
class PdfSizeTest extends TestCase
{
    public function test_pdfs_embed_font_subsets_only(): void
    {
        $this->assertTrue(config('dompdf.options.enable_font_subsetting'));

        $owner = $this->userWithRole('Inventory Manager');
        $asset = app(AssetService::class)->create(['name' => 'Ưu tiên kiểm định máy chiếu phòng họp', 'purchase_cost' => 1], $owner);
        $pdf = $this->actingAs($owner)->post(route('assets.labels'), ['ids' => [$asset->id]])->assertOk()->getContent();

        $this->assertLessThan(150 * 1024, strlen($pdf), 'PDF phải nhỏ (font đã subset), trước đây ~870 KB');
        $this->assertMatchesRegularExpression('/\/BaseFont\s*\/[A-Z]{6}\+DejaVu/', $pdf, 'Font phải được nhúng dạng subset');

        // Mỗi ký tự tiếng Việt trong tên tài sản phải có glyph trong font subset: bảng CIDToGIDMap (CID = mã Unicode) trỏ tới GID ≠ 0
        $maps = [];
        preg_match_all('/\/CIDToGIDMap\s+(\d+) 0 R/', $pdf, $refs);
        foreach (array_unique($refs[1]) as $id) {
            preg_match('/\n'.$id.' 0 obj\s*<<.*?>>\s*stream\r?\n(.*?)endstream/s', $pdf, $m);
            $maps[] = (string) @gzuncompress(rtrim($m[1] ?? '', "\r\n"));
        }
        $this->assertNotEmpty(array_filter($maps), 'Thiếu bảng CIDToGIDMap');
        $hasGlyph = fn (int $cp) => collect($maps)->contains(fn ($d) => strlen($d) > $cp * 2 + 1 && ((ord($d[$cp * 2]) << 8) | ord($d[$cp * 2 + 1])) !== 0);
        foreach (['á', 'ế', 'ò', 'ọ', 'Ư', 'ể', 'đ', 'ị'] as $ch) {
            $this->assertTrue($hasGlyph(mb_ord($ch)), "Font subset thiếu ký tự “{$ch}”");
        }
        $this->assertFalse($hasGlyph(mb_ord('Ỹ')), 'Ký tự không dùng không được nhúng');
    }
}
