<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VectorService
{
    protected $collectionName = 'knowledge_base';
    protected $qdrantUrl;

    public function __construct()
    {
        $this->qdrantUrl = env('QDRANT_HOST', 'http://127.0.0.1:6333');
    }

    /**
     * 🧠 تبدیل متن خام به بردار عددی (Embedding)
     */
    public function getEmbedding(string $text): array
    {
        try {
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.gapgpt.app/v1/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => $text
            ]);

            return $response->json('data.0.embedding') ?? [];
        } catch (\Exception $e) {
            Log::error('Embedding Generation Failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 🤖 متد جادویی استخراج متن اسناد از طریق هوش مصنوعی (AI File OCR Parser)
     */
    public function extractTextViaAI(string $absolutePath): string
    {
        try {
            Log::info("🤖 [AI PARSER] Uploading document file via Multipart to AI Server...");

            // ارسال استاندارد فایل مالتی‌پارت همراه با پرامپت استخراج متن در بخش چت ساختاریافته
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                ])
                ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
                ->post('https://api.gapgpt.app/v1/chat/completions', [
                    'model' => 'gpt-4o', // استفاده از مدل مالتی‌مدیال جهت خوانش انواع اسناد متنی و تصویری
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "تو یک ربات پیشرفته استخراج متون (OCR) برای سیستم‌های RAG هستی. فایلی که کاربر ضمیمه کرده را به دقت بخوان و تمامی متن‌های خوانای فارسی یا انگلیسی داخل آن را عینا، بدون خلاصه کردن، بدون حذف جزئیات و بدون هیچ‌گونه کلام اضافی (مثل سلام یا توضیحات درباره فایل) استخراج کن و فقط متن خالص سند را برگردان."
                        ],
                        [
                            'role' => 'user',
                            'content' => "لطفا متن کامل این فایل را استخراج کن."
                        ]
                    ],
                    'temperature' => 0.1
                ]);

            if ($response->successful()) {
                $usage = $response->json('usage') ?? [];
                Log::info("📊 [AI OCR TOKEN USAGE] Process Completed!", [
                    'prompt_tokens' => data_get($usage, 'prompt_tokens', 0),
                    'completion_tokens' => data_get($usage, 'completion_tokens', 0),
                    'total_tokens' => data_get($usage, 'total_tokens', 0)
                ]);

                return $response->json('choices.0.message.content') ?? "";
            }

            Log::error("❌ [AI PARSER] API returned error response: " . $response->body());
            return "";
        } catch (\Exception $e) {
            Log::error("❌ [AI PARSER EXCEPTION] Failed to talk with AI Server: " . $e->getMessage());
            return "";
        }
    }

    /**
     * 🛠️ ساخت کالکشن اولیه در دیتابیس برداری Qdrant
     */
    public function createCollectionIfNeeded()
    {
        try {
            $checkResponse = Http::get("{$this->qdrantUrl}/collections");
            $collections = $checkResponse->json('result.collections') ?? [];
            $existingNames = array_column($collections, 'name');

            if (!in_array($this->collectionName, $existingNames)) {
                Http::put("{$this->qdrantUrl}/collections/{$this->collectionName}", [
                    'vectors' => [
                        'content' => [
                            'size' => 1536,
                            'distance' => 'Cosine'
                        ]
                    ]
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Qdrant Collection Creation Skip/Failed: ' . $e->getMessage());
        }
    }

    /**
     * 🚀 متد نهایی و قطعی RAG: سازگار با ساختار فایل‌های ویندوز لوکال
     */
    public function indexText(int $id, string $title, ?string $content, ?string $filePath = null)
    {
        try {
            $this->createCollectionIfNeeded();
            $fullText = "";

            // 📄 ۱. اصلاح طلایی: دور زدن باگ Storage لاراول روی ویندوز با مسیر مقتدرانه storage_path
            if (!empty($filePath)) {
                // تبدیل آدرس نسبی فایلمنت به آدرس مطلق و واقعی ویندوز
                $absolutePath = storage_path('app/public/' . $filePath);
                
                if (file_exists($absolutePath)) {
                    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
                    Log::info("📂 [RAG SYSTEM] File found physically at: {$absolutePath} (Format: .{$extension})");

                    // الف: پردازش فایل‌های متنی ساده و CSV
                    if (in_array($extension, ['txt', 'csv'])) {
                        $fileContent = file_get_contents($absolutePath);
                        // اصلاح انکودینگ کلمات فارسی
                        $fullText = mb_convert_encoding($fileContent, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1256');
                        Log::info("📝 [LOCAL PARSE] Plain text extracted successfully from ID: {$id}");
                    } 
                    // ب: پردازش فایل‌های Word (.docx) بدون نیاز به پکیج، به روش فوق‌سریع بومی
                    elseif ($extension === 'docx') {
                        $wordTextCollector = "";
                        $zip = new \ZipArchive();
                        if ($zip->open($absolutePath) === true) {
                            $xmlContent = $zip->getFromName('word/document.xml');
                            if ($xmlContent) {
                                preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/', $xmlContent, $matches);
                                if (!empty($matches)) {
                                    $wordTextCollector = implode(' ', $matches);
                                }
                            }
                            $zip->close();
                        }
                        $fullText = $wordTextCollector;
                        Log::info("💼 [LOCAL PARSE] DOCX text extracted successfully from ID: {$id}");
                    }
                    // ج: پردازش فایل‌های PDF
                    elseif ($extension === 'pdf') {
                        $parser = new \Smalot\PdfParser\Parser();
                        $pdf = $parser->parseFile($absolutePath);
                        $pdfTextCollector = "";
                        foreach ($pdf->getPages() as $page) {
                            $pdfTextCollector .= $page->getText() . " ";
                        }
                        $fullText = $pdfTextCollector;
                        Log::info("📄 [LOCAL PARSE] PDF text extracted successfully from ID: {$id}");
                    }
                } else {
                    Log::warning("❌ [RAG PATH ERROR] File path exists in DB but physical file not found at: {$absolutePath}");
                }
            }

            // 💡 ۲. استراتژی جایگزین (Fail-Safe): اگر متن فایل خالی بود، برو روی متن دستی فیلامنت
            if (empty(trim($fullText)) && !empty($content)) {
                $fullText = trim(preg_replace('/\s+/', ' ', $content));
                Log::info("💡 [RAG FALLBACK] Using manually entered content field for ID: {$id}");
            }

            // ۳. لایه محافظ نهایی سیستم
            if (empty(trim($fullText))) {
                Log::warning("⚠️ [RAG INDEX] Aborted. No usable content could be found for ID: {$id}");
                return false;
            }

            // ✂️ ۴. مکانیزم تکه‌تکه کردن هوشمند متن (Text Chunking)
            $fullText = trim(preg_replace('/\s+/', ' ', $fullText));
            $chunkSize = 1000;
            $overlap = 150;
            $chunks = [];
            $textLength = mb_strlen($fullText);
            $pointer = 0;

            while ($pointer < $textLength) {
                $chunks[] = mb_substr($fullText, $pointer, $chunkSize);
                $pointer += ($chunkSize - $overlap);
            }

            Log::info("✂️ [RAG CHUNKING] Document ID {$id} split into " . count($chunks) . " chunks.");

            // ۵. ارسال نهایی تکه‌ها به Qdrant لوکال
            foreach ($chunks as $index => $chunkText) {
                $embedding = $this->getEmbedding($chunkText);
                if (empty($embedding)) continue;

                $pointId = ($id * 1000) + $index;

                Http::withoutVerifying()->put("{$this->qdrantUrl}/collections/{$this->collectionName}/points?wait=true", [
                    'points' => [
                        [
                            'id' => $pointId,
                            'vector' => [
                                'content' => $embedding
                            ],
                            'payload' => [
                                'knowledge_id' => $id,
                                'chunk_index' => $index,
                                'title' => $title . " (بخش " . ($index + 1) . ")",
                                'content' => $chunkText
                            ]
                        ]
                    ]
                ]);
            }

            Log::info("🎯 All chunks for Document ID {$id} successfully indexed in Qdrant.");
            return true;
        } catch (\Exception $e) {
            Log::error('Advanced Indexing Process Failed: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * 🔍 جستجوی معنایی در پایگاه دانش بر اساس متن سوال مشتری
     */
    public function searchRelatedKnowledge(string $queryText, int $limit = 2): string
    {
        try {
            $queryEmbedding = $this->getEmbedding($queryText);
            if (empty($queryEmbedding)) return "";

            $response = Http::withoutVerifying()->post("{$this->qdrantUrl}/collections/{$this->collectionName}/points/search", [
                'vector' => [
                    'name' => 'content',
                    'vector' => $queryEmbedding
                ],
                'limit' => $limit,
                'with_payload' => true
            ]);

            $results = $response->json('result') ?? [];
            Log::info("📡 [QDRANT RAW SEARCH RESULTS]", ['count' => count($results), 'raw_data' => $results]);

            $contextText = "";

            foreach ($results as $index => $result) {
                $score = $result['score'] ?? 0;
                $title = data_get($result, 'payload.title', 'قانون مهاجرتی');
                $content = data_get($result, 'payload.content', '');
                
                if ($score > 0.4) {
                    $percentage = round($score * 100, 1);
                    $contextText .= "--- قانون شماره " . ($index + 1) . ": {$title} (میزان تطابق: {$percentage}%) ---\n{$content}\n\n";
                }
            }

            return $contextText;
        } catch (\Exception $e) {
            Log::error('Qdrant Semantic Search Failed: ' . $e->getMessage());
            return "";
        }
    }
}