<?php
session_start();

// Cek apakah sudah login. Kalau belum, lempar ke halaman login.
if (empty($_SESSION["logged_in"])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION["username"] ?? "Admin";

/* ==========================================================
   ALGORITMA PERINGKAS CATATAN (EXTRACTIVE SUMMARIZATION)
   Murni PHP, tanpa API/library eksternal.

   Cara kerja singkat:
   1. Pecah teks jadi kalimat.
   2. Hitung frekuensi setiap kata penting (stopword dibuang).
   3. Skor tiap kalimat = rata-rata skor kata-kata di dalamnya,
      ditambah sedikit bonus untuk kalimat di awal paragraf
      (biasanya memuat inti pembahasan).
   4. Ambil N kalimat dengan skor tertinggi, lalu susun ulang
      sesuai urutan asli supaya tetap enak dibaca.
   ========================================================== */

// Daftar stopword Bahasa Indonesia (kata umum yang tidak menambah makna penting)
function get_stopwords() {
    return array_flip([
        "yang","untuk","pada","ke","para","namun","menurut","antara","dia","dua",
        "ia","seperti","jika","jika","sehingga","kembali","dan","tidak","ini","karena",
        "kepada","oleh","saat","harus","sementara","setelah","belum","kami","sekitar",
        "bagi","serta","di","dari","telah","sebagai","masih","hal","ketika","adalah",
        "itu","dalam","bahwa","atau","dengan","akan","juga","sudah","saya","kita",
        "anda","mereka","kamu","atas","tersebut","dapat","bisa","ada","lagi","namun",
        "bagaimana","apa","siapa","kenapa","mengapa","yaitu","yakni","maka","begitu",
        "pun","per","tiap","setiap","suatu","satu","tetapi","melainkan","agar","supaya"
    ]);
}

// Pecah teks jadi array kalimat
function split_sentences($text) {
    $text = preg_replace('/\s+/', ' ', trim($text));
    // Pisah berdasar tanda titik/tanya/seru yang diikuti spasi + huruf kapital atau akhir teks
    $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z0-9"\'(])/u', $text);
    $sentences = array_filter(array_map('trim', $sentences), fn($s) => mb_strlen($s) > 0);
    return array_values($sentences);
}

// Ambil kata-kata bersih dari sebuah string (lowercase, tanpa tanda baca)
function tokenize($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $words = preg_split('/\s+/u', trim($text));
    return array_filter($words, fn($w) => mb_strlen($w) > 2);
}

function ringkas_teks($text, $target_ratio = 0.3) {
    $stopwords = get_stopwords();
    $sentences = split_sentences($text);
    $total_kalimat = count($sentences);

    if ($total_kalimat <= 3) {
        // Teks terlalu pendek untuk diringkas, kembalikan apa adanya
        return [
            'ringkasan' => $text,
            'total_kalimat_asli' => $total_kalimat,
            'total_kalimat_ringkas' => $total_kalimat,
            'jumlah_kata_asli' => count(tokenize($text)),
            'jumlah_kata_ringkas' => count(tokenize($text)),
        ];
    }

    // 1. Hitung frekuensi kata (tanpa stopword) di seluruh teks
    $freq = [];
    foreach ($sentences as $s) {
        foreach (tokenize($s) as $w) {
            if (isset($stopwords[$w])) continue;
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }
    }
    $max_freq = $freq ? max($freq) : 1;
    foreach ($freq as $w => $f) {
        $freq[$w] = $f / $max_freq; // normalisasi 0..1
    }

    // 2. Skor tiap kalimat
    $scores = [];
    foreach ($sentences as $i => $s) {
        $words = tokenize($s);
        $content_words = array_filter($words, fn($w) => !isset($stopwords[$w]));
        $content_words = array_values($content_words);

        if (count($content_words) === 0) {
            $scores[$i] = 0;
            continue;
        }

        $sum = 0;
        foreach ($content_words as $w) {
            $sum += $freq[$w] ?? 0;
        }
        $skor = $sum / count($content_words);

        // Bonus posisi: 3 kalimat pertama dan kalimat penutup sedikit diprioritaskan
        if ($i < 3) $skor += 0.15;
        if ($i === $total_kalimat - 1) $skor += 0.05;

        $scores[$i] = $skor;
    }

    // 3. Tentukan jumlah kalimat ringkasan (minimal 2 kalimat)
    $jumlah_ringkas = max(2, (int) round($total_kalimat * $target_ratio));
    $jumlah_ringkas = min($jumlah_ringkas, $total_kalimat);

    // 4. Ambil index kalimat dengan skor tertinggi
    arsort($scores);
    $top_indexes = array_slice(array_keys($scores), 0, $jumlah_ringkas);
    sort($top_indexes); // urutkan kembali sesuai urutan asli teks

    $hasil_kalimat = array_map(fn($i) => $sentences[$i], $top_indexes);
    $ringkasan = implode(' ', $hasil_kalimat);

    return [
        'ringkasan' => $ringkasan,
        'total_kalimat_asli' => $total_kalimat,
        'total_kalimat_ringkas' => count($hasil_kalimat),
        'jumlah_kata_asli' => count(tokenize($text)),
        'jumlah_kata_ringkas' => count(tokenize($ringkasan)),
    ];
}

/* ==========================================================
   EKSTRAKSI TEKS DARI FILE UPLOAD (txt, docx, pdf)
   Semua ditulis native PHP, tanpa library/Composer.
   ========================================================== */

function extract_txt($filepath) {
    $content = file_get_contents($filepath);
    // Bersihkan BOM UTF-8 kalau ada
    return preg_replace('/^\xEF\xBB\xBF/', '', $content);
}

/**
 * Baca satu entry tertentu dari file ZIP secara manual, tanpa ekstensi php-zip.
 * Dipakai sebagai fallback untuk baca .docx kalau class ZipArchive tidak
 * tersedia (banyak instalasi XAMPP default belum mengaktifkan ext-zip).
 */
function zip_get_entry_manual($filepath, $entry_name) {
    $data = file_get_contents($filepath);
    if ($data === false) {
        throw new Exception("File tidak bisa dibaca.");
    }

    // Cari "End of Central Directory" record (signature PK\x05\x06) dari belakang
    $eocd_pos = strrpos($data, "\x50\x4b\x05\x06");
    if ($eocd_pos === false) {
        throw new Exception("File .docx tidak valid (bukan struktur ZIP yang benar).");
    }

    $eocd = substr($data, $eocd_pos, 22);
    $entry_count = unpack('v', substr($eocd, 10, 2))[1];
    $cd_offset = unpack('V', substr($eocd, 16, 4))[1];

    $pos = $cd_offset;
    for ($i = 0; $i < $entry_count; $i++) {
        if (substr($data, $pos, 4) !== "\x50\x4b\x01\x02") {
            break; // bukan lagi central directory entry, berhenti
        }

        $header = substr($data, $pos, 46);
        $comp_method = unpack('v', substr($header, 10, 2))[1];
        $comp_size = unpack('V', substr($header, 20, 4))[1];
        $name_len = unpack('v', substr($header, 28, 2))[1];
        $extra_len = unpack('v', substr($header, 30, 2))[1];
        $comment_len = unpack('v', substr($header, 32, 2))[1];
        $local_offset = unpack('V', substr($header, 42, 4))[1];
        $name = substr($data, $pos + 46, $name_len);

        if ($name === $entry_name) {
            $lfh = substr($data, $local_offset, 30);
            if (substr($lfh, 0, 4) !== "\x50\x4b\x03\x04") {
                throw new Exception("Struktur ZIP di dalam .docx tidak valid.");
            }
            $lfh_name_len = unpack('v', substr($lfh, 26, 2))[1];
            $lfh_extra_len = unpack('v', substr($lfh, 28, 2))[1];
            $data_start = $local_offset + 30 + $lfh_name_len + $lfh_extra_len;
            $compressed = substr($data, $data_start, $comp_size);

            if ($comp_method === 0) {
                return $compressed; // disimpan tanpa kompresi
            } elseif ($comp_method === 8) {
                $result = @gzinflate($compressed);
                if ($result === false) {
                    throw new Exception("Gagal membuka isi file .docx (deflate error).");
                }
                return $result;
            }
            throw new Exception("Metode kompresi ZIP ini belum didukung.");
        }

        $pos += 46 + $name_len + $extra_len + $comment_len;
    }

    throw new Exception("Tidak ditemukan konten dokumen di dalam file .docx ini.");
}

function extract_docx($filepath) {
    $xml = false;

    // Cara utama: pakai ekstensi ZipArchive kalau tersedia di server
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filepath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
        }
    }

    // Fallback: parser ZIP manual (dipakai kalau ekstensi php-zip belum aktif di XAMPP)
    if ($xml === false) {
        $xml = zip_get_entry_manual($filepath, 'word/document.xml');
    }

    if ($xml === false || $xml === '') {
        throw new Exception("Tidak ditemukan konten teks di dalam file .docx ini.");
    }

    // Ganti akhir paragraf (</w:p>) jadi baris baru sebelum tag dibuang
    $xml = str_replace('</w:p>', "</w:p>\n", $xml);
    // Tab antar sel tabel
    $xml = str_replace('</w:tc>', "\t", $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    return trim($text);
}

/**
 * Decode ASCII85 (dipakai sebagian generator PDF sebelum FlateDecode).
 */
function ascii85_decode($data) {
    $data = trim($data);
    $data = preg_replace('/^<~/', '', $data);
    $data = preg_replace('/~>$/', '', $data);
    $data = preg_replace('/\s+/', '', $data);

    $result = '';
    $len = strlen($data);
    $i = 0;
    while ($i < $len) {
        if ($data[$i] === 'z') {
            $result .= "\0\0\0\0";
            $i++;
            continue;
        }
        $chunk = substr($data, $i, 5);
        $chunk_len = strlen($chunk);
        $padded = str_pad($chunk, 5, 'u');
        $num = 0;
        for ($j = 0; $j < 5; $j++) {
            $num = $num * 85 + (ord($padded[$j]) - 33);
        }
        $bytes = pack('N', $num & 0xFFFFFFFF);
        $result .= substr($bytes, 0, max(0, $chunk_len - 1));
        $i += 5;
    }
    return $result;
}

function decode_literal_string($s) {
    $s = substr($s, 1, -1);
    return preg_replace_callback('/\\\\([0-7]{1,3}|.)/', function ($m) {
        if (ctype_digit($m[1])) return chr(octdec($m[1]));
        return match ($m[1]) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '(' => '(',
            ')' => ')',
            '\\' => '\\',
            default => $m[1],
        };
    }, $s);
}

function decode_hex_string($s, $unicode_map, $byte_width = 2) {
    $hex = substr($s, 1, -1);
    $hex = preg_replace('/\s+/', '', $hex);
    $chunk_hexlen = $byte_width * 2;
    if (strlen($hex) % $chunk_hexlen !== 0) {
        $hex = str_pad($hex, strlen($hex) + ($chunk_hexlen - strlen($hex) % $chunk_hexlen), '0');
    }

    $result = '';
    for ($i = 0; $i < strlen($hex); $i += $chunk_hexlen) {
        $code = hexdec(substr($hex, $i, $chunk_hexlen));
        if (isset($unicode_map[$code])) {
            $result .= $unicode_map[$code];
        }
        // Kalau tidak ada di peta ToUnicode, glyph ini dilewati (tidak bisa dipastikan karakternya)
    }
    return $result;
}

// Bangun tabel kode-glyph -> karakter asli dari objek ToUnicode CMap yang ada di PDF.
// Mengembalikan [map, byte_width] karena font subset ada yang pakai kode 1 byte, ada yang 2 byte.
function build_unicode_map($decoded_streams) {
    $map = [];
    $byte_width = 2; // default umum (Identity-H)

    foreach ($decoded_streams as $decoded) {
        if (stripos($decoded, 'beginbfchar') === false && stripos($decoded, 'beginbfrange') === false) {
            continue;
        }

        // Deteksi lebar byte dari codespacerange, mis. <00><FF> = 1 byte, <0000><FFFF> = 2 byte
        if (preg_match('/begincodespacerange\s*<([0-9A-Fa-f]+)>/', $decoded, $csr)) {
            $byte_width = (int) (strlen($csr[1]) / 2);
            if ($byte_width < 1) $byte_width = 2;
        }

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $decoded, $blocks)) {
            foreach ($blocks[1] as $block) {
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER);
                foreach ($pairs as $p) {
                    $src = hexdec($p[1]);
                    $bytes = @hex2bin(strlen($p[2]) % 2 === 0 ? $p[2] : '0' . $p[2]);
                    if ($bytes === false) continue;
                    $map[$src] = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE') ?: '';
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $decoded, $blocks)) {
            foreach ($blocks[1] as $block) {
                preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $triples, PREG_SET_ORDER);
                foreach ($triples as $t) {
                    $srcStart = hexdec($t[1]);
                    $srcEnd = hexdec($t[2]);
                    $dstStart = hexdec($t[3]);
                    // Batasi supaya tidak looping ribuan kode kalau datanya aneh
                    if ($srcEnd - $srcStart > 5000) continue;
                    for ($c = $srcStart; $c <= $srcEnd; $c++) {
                        $codepoint = $dstStart + ($c - $srcStart);
                        $map[$c] = @mb_convert_encoding(pack('n', $codepoint), 'UTF-8', 'UTF-16BE') ?: '';
                    }
                }
            }
        }
    }
    return [$map, $byte_width];
}

/**
 * Ekstraksi teks dari PDF secara manual (tanpa library eksternal).
 *
 * Cara kerja:
 * 1. Ambil semua objek stream, decode sesuai filter (FlateDecode / ASCII85Decode).
 * 2. Bangun tabel ToUnicode CMap (kalau ada) — dipakai font subset hasil
 *    export dari Word/LibreOffice yang menggambar teks pakai kode hex,
 *    bukan karakter ASCII biasa.
 * 3. Telusuri tiap content stream, tarik teks dari operator Tj/TJ —
 *    baik dalam bentuk string literal ( ... ) maupun string hex < ... >.
 *
 * Catatan: hanya efektif untuk PDF berbasis teks (bukan hasil scan/foto).
 */
function extract_pdf($filepath) {
    $data = file_get_contents($filepath);
    if ($data === false) {
        throw new Exception("File PDF tidak bisa dibaca.");
    }

    preg_match_all('/stream\r?\n(.*?)endstream/s', $data, $matches, PREG_OFFSET_CAPTURE);
    if (empty($matches[1])) {
        throw new Exception("Tidak ditemukan konten yang bisa dibaca di PDF ini.");
    }

    // 1. Decode semua stream terlebih dahulu
    $decoded_streams = [];
    foreach ($matches[1] as $match) {
        $stream = rtrim($match[0], "\r\n");
        $offset = $match[1];

        $context_start = max(0, $offset - 500);
        $context = substr($data, $context_start, $offset - $context_start);

        $decoded = $stream;
        if (stripos($context, 'ASCII85Decode') !== false) {
            $decoded = ascii85_decode($decoded);
        }
        if (stripos($context, 'FlateDecode') !== false) {
            $tmp = @gzuncompress($decoded);
            if ($tmp === false) $tmp = @gzinflate($decoded);
            if ($tmp !== false) $decoded = $tmp;
        }

        $decoded_streams[] = $decoded;
    }

    // 2. Bangun peta ToUnicode (kalau font-nya subset/embedded)
    [$unicode_map, $byte_width] = build_unicode_map($decoded_streams);

    // 3. Tarik teks dari tiap content stream
    $full_text = '';
    foreach ($decoded_streams as $decoded) {
        if (!str_contains($decoded, 'Tj') && !str_contains($decoded, 'TJ')) {
            continue;
        }

        preg_match_all(
            '/(\((?:\\\\.|[^()\\\\])*\)|<[0-9A-Fa-f\s]+>|\[(?:[^\[\]]|\[[^\[\]]*\])*\])\s*(Tj|TJ)\b/s',
            $decoded,
            $ops,
            PREG_SET_ORDER
        );

        foreach ($ops as $op) {
            $operand = $op[1];
            $piece = '';

            if ($operand[0] === '(') {
                $piece = decode_literal_string($operand);
            } elseif ($operand[0] === '<') {
                $piece = decode_hex_string($operand, $unicode_map, $byte_width);
            } elseif ($operand[0] === '[') {
                preg_match_all('/\((?:\\\\.|[^()\\\\])*\)|<[0-9A-Fa-f\s]+>/', $operand, $elems);
                foreach ($elems[0] as $e) {
                    $piece .= $e[0] === '(' ? decode_literal_string($e) : decode_hex_string($e, $unicode_map, $byte_width);
                }
            }

            $full_text .= $piece . ' ';
        }
        $full_text .= "\n";
    }

    $full_text = trim(preg_replace('/[ \t]+/', ' ', $full_text));
    if (mb_strlen($full_text) < 20) {
        throw new Exception("Teks di PDF ini tidak bisa terbaca — kemungkinan PDF hasil scan/foto. Coba gunakan menu 'Scan Dokumen' untuk PDF jenis ini.");
    }

    return $full_text;
}

function extract_text_from_upload($filepath, $ext) {
    return match ($ext) {
        'txt' => extract_txt($filepath),
        'docx' => extract_docx($filepath),
        'pdf' => extract_pdf($filepath),
        default => throw new Exception("Format file tidak didukung."),
    };
}


// ==========================================================
// PROSES FORM
// ==========================================================
$hasil = null;
$catatan_input = '';
$error = '';
$nama_file_asli = '';
$ratio = isset($_POST['panjang']) ? (float) $_POST['panjang'] : 0.3;
$mode = $_POST['mode'] ?? 'teks';

$MAX_SIZE = 10 * 1024 * 1024; // 10 MB
$ALLOWED_EXT = ['txt', 'docx', 'pdf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($mode === 'file' && isset($_FILES['dokumen']) && $_FILES['dokumen']['error'] !== UPLOAD_ERR_NO_FILE) {

        $file = $_FILES['dokumen'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Gagal mengupload file. Coba lagi.";
        } elseif ($file['size'] > $MAX_SIZE) {
            $error = "Ukuran file maksimal 10 MB.";
        } else {
            $nama_file_asli = $file['name'];
            $ext = strtolower(pathinfo($nama_file_asli, PATHINFO_EXTENSION));

            if (!in_array($ext, $ALLOWED_EXT)) {
                $error = "Format file harus .txt, .docx, atau .pdf.";
            } else {
                try {
                    $catatan_input = extract_text_from_upload($file['tmp_name'], $ext);
                    if (mb_strlen(trim($catatan_input)) < 20) {
                        $error = "Isi dokumen terlalu pendek atau tidak terbaca untuk diringkas.";
                    } else {
                        $hasil = ringkas_teks($catatan_input, $ratio);
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

    } elseif (isset($_POST['catatan']) && trim($_POST['catatan']) !== '') {
        $catatan_input = trim($_POST['catatan']);
        if (mb_strlen($catatan_input) < 20) {
            $error = "Teks terlalu pendek untuk diringkas. Masukkan minimal beberapa kalimat.";
        } else {
            $hasil = ringkas_teks($catatan_input, $ratio);
        }
    } else {
        $error = "Silakan tempel teks atau upload dokumen terlebih dahulu.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peringkas Catatan - Arsip Pro</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    margin:0;
    font-family: 'Segoe UI', sans-serif;
    background:#0f172a;
    color:#fff;
}
.main {
    max-width:900px;
    margin:0 auto;
    padding:30px 20px 60px;
}
.topbar-page {
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:25px;
}
.topbar-page h2 {
    color:#38bdf8;
    margin:0;
    display:flex;
    align-items:center;
    gap:10px;
}
.page-icon {
    width:40px;
    height:40px;
    object-fit:contain;
}
.back-link {
    color:#94a3b8;
    text-decoration:none;
    font-size:14px;
    display:flex;
    align-items:center;
    gap:6px;
}
.back-link:hover { color:#38bdf8; }

.box {
    background:#1e293b;
    padding:25px;
    border-radius:15px;
    margin-bottom:20px;
}
.box h3 {
    margin-top:0;
    color:#e2e8f0;
}
.mode-tabs {
    display:flex;
    gap:8px;
    margin-bottom:20px;
}
.tab-btn {
    background:#0f172a;
    border:1px solid #334155;
    color:#94a3b8;
    padding:10px 18px;
    border-radius:10px;
    cursor:pointer;
    font-size:14px;
    font-family:inherit;
}
.tab-btn.active {
    background:#38bdf8;
    color:#0f172a;
    font-weight:600;
    border-color:#38bdf8;
}

.upload-box {
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:8px;
    min-height:180px;
    border:2px dashed #334155;
    border-radius:10px;
    cursor:pointer;
    text-align:center;
    padding:20px;
    color:#cbd5e1;
    font-size:14px;
}
.upload-box:hover {
    border-color:#38bdf8;
    background:rgba(56,189,248,0.05);
}
.upload-box.dragover {
    border-color:#38bdf8;
    background:rgba(56,189,248,0.1);
}
.upload-hint {
    font-size:12px;
    color:#64748b;
}

textarea {
    width:100%;
    min-height:220px;
    background:#0f172a;
    border:1px solid #334155;
    color:#fff;
    border-radius:10px;
    padding:15px;
    font-size:14px;
    font-family:inherit;
    resize:vertical;
    box-sizing:border-box;
}
textarea:focus { outline:2px solid #38bdf8; }

.form-row {
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-top:15px;
    gap:15px;
    flex-wrap:wrap;
}
.length-options {
    display:flex;
    gap:8px;
}
.length-options label {
    background:#0f172a;
    border:1px solid #334155;
    padding:8px 14px;
    border-radius:8px;
    cursor:pointer;
    font-size:13px;
    color:#cbd5e1;
}
.length-options input {
    display:none;
}
.length-options input:checked + span {
    color:#0f172a;
}
.length-options input:checked ~ label,
.length-options label:has(input:checked) {
    background:#38bdf8;
    color:#0f172a;
    font-weight:600;
}

.btn-submit {
    background:#38bdf8;
    color:#0f172a;
    border:none;
    padding:12px 25px;
    border-radius:10px;
    font-weight:600;
    cursor:pointer;
    font-size:14px;
}
.btn-submit:hover { background:#0ea5e9; }

.stat-row {
    display:flex;
    gap:15px;
    margin-bottom:15px;
    flex-wrap:wrap;
}
.stat-card {
    background:#0f172a;
    border:1px solid #334155;
    border-radius:10px;
    padding:12px 18px;
    flex:1;
    min-width:140px;
}
.stat-card .num {
    font-size:20px;
    font-weight:700;
    color:#38bdf8;
}
.stat-card .label {
    font-size:12px;
    color:#94a3b8;
}

.result-text {
    background:#0f172a;
    border:1px solid #334155;
    border-radius:10px;
    padding:18px;
    line-height:1.7;
    font-size:15px;
    color:#e2e8f0;
}

.error-msg {
    background:#450a0a;
    border:1px solid #ef4444;
    color:#fecaca;
    padding:12px 15px;
    border-radius:10px;
    margin-bottom:15px;
}

.copy-btn {
    margin-top:12px;
    background:transparent;
    border:1px solid #38bdf8;
    color:#38bdf8;
    padding:8px 16px;
    border-radius:8px;
    cursor:pointer;
    font-size:13px;
}
.copy-btn:hover { background:#38bdf8; color:#0f172a; }
</style>
</head>
<body>

<div class="main">

    <div class="topbar-page">
        <h2><img src="icon/peringkas-catatan.png" alt="Peringkas Catatan" class="page-icon"> Peringkas Catatan</h2>
        <a href="admin.php" class="back-link"><i class="fa fa-arrow-left"></i> Kembali ke Dashboard</a>
    </div>

    <div class="box">
        <div class="mode-tabs">
            <button type="button" class="tab-btn active" id="tabTeksBtn" onclick="gantiMode('teks')"><i class="fa fa-keyboard"></i> Tempel Teks</button>
            <button type="button" class="tab-btn" id="tabFileBtn" onclick="gantiMode('file')"><i class="fa fa-file-arrow-up"></i> Upload Dokumen</button>
        </div>

        <?php if ($error): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="formRingkas">
            <input type="hidden" name="mode" id="modeInput" value="<?= htmlspecialchars($mode) ?>">

            <div id="panelTeks" class="mode-panel">
                <textarea name="catatan" placeholder="Tempel catatan rapat, materi kuliah, atau teks panjang lainnya di sini..."><?= $mode === 'teks' ? htmlspecialchars($catatan_input) : '' ?></textarea>
            </div>

            <div id="panelFile" class="mode-panel" style="display:none;">
                <label class="upload-box" for="fileInput">
                    <i class="fa fa-cloud-arrow-up" style="font-size:32px; color:#38bdf8;"></i>
                    <span id="fileLabelText">Klik untuk pilih dokumen, atau tarik file ke sini</span>
                    <span class="upload-hint">Format didukung: .txt, .docx, .pdf (maks. 10 MB)</span>
                </label>
                <input type="file" name="dokumen" id="fileInput" accept=".txt,.docx,.pdf" style="display:none;" onchange="tampilkanNamaFile(this)">
            </div>

            <div class="form-row">
                <div class="length-options">
                    <label><input type="radio" name="panjang" value="0.2" <?= $ratio == 0.2 ? 'checked' : '' ?>><span>Singkat (20%)</span></label>
                    <label><input type="radio" name="panjang" value="0.3" <?= $ratio == 0.3 || !isset($_POST['panjang']) ? 'checked' : '' ?>><span>Sedang (30%)</span></label>
                    <label><input type="radio" name="panjang" value="0.5" <?= $ratio == 0.5 ? 'checked' : '' ?>><span>Panjang (50%)</span></label>
                </div>
                <button type="submit" class="btn-submit"><i class="fa fa-wand-magic-sparkles"></i> Ringkas Sekarang</button>
            </div>
        </form>
    </div>

    <?php if ($hasil): ?>
    <div class="box">
        <h3>Hasil Ringkasan<?= $nama_file_asli ? ' — ' . htmlspecialchars($nama_file_asli) : '' ?></h3>

        <div class="stat-row">
            <div class="stat-card">
                <div class="num"><?= $hasil['total_kalimat_asli'] ?> → <?= $hasil['total_kalimat_ringkas'] ?></div>
                <div class="label">Jumlah Kalimat</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $hasil['jumlah_kata_asli'] ?> → <?= $hasil['jumlah_kata_ringkas'] ?></div>
                <div class="label">Jumlah Kata</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $hasil['jumlah_kata_asli'] > 0 ? round(100 - ($hasil['jumlah_kata_ringkas'] / $hasil['jumlah_kata_asli'] * 100)) : 0 ?>%</div>
                <div class="label">Teks Dipangkas</div>
            </div>
        </div>

        <div class="result-text" id="hasilRingkasan"><?= nl2br(htmlspecialchars($hasil['ringkasan'])) ?></div>
        <button type="button" class="copy-btn" onclick="salinRingkasan()"><i class="fa fa-copy"></i> Salin Teks</button>
    </div>
    <?php endif; ?>

</div>

<script>
function salinRingkasan() {
    const el = document.getElementById('hasilRingkasan');
    if (!el) return;
    const text = el.innerText;
    navigator.clipboard.writeText(text).then(() => {
        alert('Ringkasan berhasil disalin!');
    });
}

function gantiMode(mode) {
    document.getElementById('modeInput').value = mode;
    document.getElementById('panelTeks').style.display = mode === 'teks' ? 'block' : 'none';
    document.getElementById('panelFile').style.display = mode === 'file' ? 'block' : 'none';
    document.getElementById('tabTeksBtn').classList.toggle('active', mode === 'teks');
    document.getElementById('tabFileBtn').classList.toggle('active', mode === 'file');
}

function tampilkanNamaFile(input) {
    const label = document.getElementById('fileLabelText');
    if (input.files && input.files.length > 0) {
        label.textContent = input.files[0].name;
    }
}

// Aktifkan tab sesuai mode terakhir (kalau habis submit upload lalu ada error, tetap di tab upload)
document.addEventListener('DOMContentLoaded', function () {
    const modeAwal = document.getElementById('modeInput').value || 'teks';
    gantiMode(modeAwal);

    // Drag & drop
    const uploadBox = document.querySelector('.upload-box');
    const fileInput = document.getElementById('fileInput');
    if (uploadBox && fileInput) {
        ['dragenter', 'dragover'].forEach(evt => {
            uploadBox.addEventListener(evt, function (e) {
                e.preventDefault();
                uploadBox.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            uploadBox.addEventListener(evt, function (e) {
                e.preventDefault();
                uploadBox.classList.remove('dragover');
            });
        });
        uploadBox.addEventListener('drop', function (e) {
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                tampilkanNamaFile(fileInput);
            }
        });
    }
});
</script>

</body>
</html>