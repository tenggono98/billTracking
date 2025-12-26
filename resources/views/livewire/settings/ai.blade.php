<?php

use App\Models\Setting;
use Livewire\Volt\Component;

new class extends Component {
    public $ai_api_key = '';
    public $ai_gemini_api_key = '';
    public $ai_gemini_model = 'gemini-1.5-flash';
    public $ai_prompt_transfer = '';
    public $ai_prompt_bill = '';
    public $ai_fallback_prompt = '';
    
    public $testing_vision = false;
    public $testing_gemini = false;
    public $vision_test_result = null;
    public $gemini_test_result = null;
    
    public $loading_models = false;
    public $gemini_models = [];
    public $models_error = null;

    public function mount()
    {
        $this->ai_api_key = Setting::get('ai_api_key', '');
        $this->ai_gemini_api_key = Setting::get('ai_gemini_api_key', '');
        $this->ai_gemini_model = Setting::get('ai_gemini_model', 'gemini-1.5-flash');
        $this->ai_prompt_transfer = Setting::get('ai_prompt_transfer', '');
        $this->ai_prompt_bill = Setting::get('ai_prompt_bill', '');
        $this->ai_fallback_prompt = Setting::get('ai_fallback_prompt', '');
        
        // Load models dynamically from Google API
        $this->loadGeminiModels();
    }

    public function updatedAiApiKey()
    {
        // Refresh models when Vision API key changes
        $this->loadGeminiModels();
    }

    public function updatedAiGeminiApiKey()
    {
        // Refresh models when Gemini API key changes
        $this->loadGeminiModels();
    }

    public function loadGeminiModels()
    {
        $this->loading_models = true;
        $this->models_error = null;
        
        try {
            // Get API key (prefer Gemini API key, fallback to Vision API key)
            $apiKey = $this->ai_gemini_api_key ?: $this->ai_api_key;
            
            if (empty($apiKey)) {
                // No API key, use default models as fallback
                $this->gemini_models = $this->getDefaultModels();
                $this->loading_models = false;
                return;
            }

            // Fetch models from Google API
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get(
                "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}"
            );

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['models']) && is_array($data['models'])) {
                    $models = [];
                    
                    foreach ($data['models'] as $model) {
                        // Only include models that support generateContent
                        // Filter out embedding models and other non-generative models
                        $name = $model['name'] ?? '';
                        $displayName = $model['displayName'] ?? $name;
                        $supportedMethods = $model['supportedGenerationMethods'] ?? [];
                        
                        // Check if model supports generateContent
                        if (in_array('generateContent', $supportedMethods)) {
                            // Extract model name (format: models/gemini-1.5-flash)
                            $modelName = str_replace('models/', '', $name);
                            
                            // Build display name with description if available
                            $description = $model['description'] ?? '';
                            if ($description) {
                                $displayName = $displayName . ($description ? ' - ' . $description : '');
                            }
                            
                            $models[$modelName] = $displayName;
                        }
                    }
                    
                    // Sort models: prefer newer versions first
                    uksort($models, function($a, $b) {
                        // Extract version numbers for sorting
                        preg_match('/(\d+)\.(\d+)/', $a, $matchA);
                        preg_match('/(\d+)\.(\d+)/', $b, $matchB);
                        
                        if (isset($matchA[1]) && isset($matchB[1])) {
                            if ($matchA[1] != $matchB[1]) {
                                return $matchB[1] - $matchA[1]; // Higher major version first
                            }
                            return ($matchB[2] ?? 0) - ($matchA[2] ?? 0); // Higher minor version first
                        }
                        
                        // Fallback: alphabetical
                        return strcmp($a, $b);
                    });
                    
                    if (!empty($models)) {
                        $this->gemini_models = $models;
                    } else {
                        // No valid models found, use defaults
                        $this->gemini_models = $this->getDefaultModels();
                        $this->models_error = 'Tidak ada model yang mendukung generateContent ditemukan.';
                    }
                } else {
                    // Invalid response format, use defaults
                    $this->gemini_models = $this->getDefaultModels();
                    $this->models_error = 'Format respons API tidak valid.';
                }
            } else {
                // API error, use defaults
                $error = $response->json();
                $errorMessage = $error['error']['message'] ?? 'Gagal memuat daftar model.';
                $this->gemini_models = $this->getDefaultModels();
                $this->models_error = $errorMessage;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection error, use defaults
            $this->gemini_models = $this->getDefaultModels();
            $this->models_error = 'Gagal terhubung ke API. Menggunakan daftar model default.';
        } catch (\Exception $e) {
            // Any other error, use defaults
            $this->gemini_models = $this->getDefaultModels();
            $this->models_error = 'Error: ' . $e->getMessage();
        } finally {
            $this->loading_models = false;
        }
    }

    public function refreshModels()
    {
        $this->loadGeminiModels();
    }

    private function getDefaultModels(): array
    {
        return [
            'gemini-1.5-pro' => 'Gemini 1.5 Pro (Most capable, slower)',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash (Fast, recommended)',
            'gemini-pro' => 'Gemini Pro (Older version)',
            'gemini-1.0-pro' => 'Gemini 1.0 Pro (Legacy)',
        ];
    }

    public function testVisionConnection()
    {
        $this->testing_vision = true;
        $this->vision_test_result = null;
        
        try {
            if (empty($this->ai_api_key)) {
                $this->vision_test_result = [
                    'success' => false,
                    'message' => 'Kunci API kosong. Silakan masukkan kunci API Google Vision Anda.'
                ];
                $this->testing_vision = false;
                return;
            }

            // Create a minimal valid 1x1 PNG image for testing
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            $testImage = base64_encode($png);
            
            $response = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://vision.googleapis.com/v1/images:annotate?key={$this->ai_api_key}", [
                'requests' => [
                    [
                        'image' => [
                            'content' => $testImage,
                        ],
                        'features' => [
                            [
                                'type' => 'TEXT_DETECTION',
                                'maxResults' => 1,
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $this->vision_test_result = [
                    'success' => true,
                    'message' => 'Koneksi berhasil! Kunci API Google Vision valid dan berfungsi.'
                ];
            } else {
                $error = $response->json();
                $errorMessage = $error['error']['message'] ?? 'Koneksi gagal. Silakan periksa kunci API Anda.';
                
                // Provide helpful error messages
                if (str_contains($errorMessage, 'API key not valid')) {
                    $errorMessage = 'Kunci API tidak valid. Silakan periksa kunci API Google Vision Anda.';
                } elseif (str_contains($errorMessage, 'PERMISSION_DENIED')) {
                    $errorMessage = 'Izin ditolak. Pastikan kunci API memiliki Vision API yang diaktifkan.';
                } elseif (str_contains($errorMessage, 'quota')) {
                    $errorMessage = 'Kuota API terlampaui. Silakan periksa penagihan Google Cloud Anda.';
                }
                
                $this->vision_test_result = [
                    'success' => false,
                    'message' => $errorMessage
                ];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->vision_test_result = [
                'success' => false,
                'message' => 'Koneksi timeout. Silakan periksa koneksi internet Anda.'
            ];
        } catch (\Exception $e) {
            $this->vision_test_result = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        } finally {
            $this->testing_vision = false;
        }
    }

    public function testGeminiConnection()
    {
        $this->testing_gemini = true;
        $this->gemini_test_result = null;
        
        try {
            $apiKey = $this->ai_gemini_api_key ?: $this->ai_api_key;
            
            if (empty($apiKey)) {
                $this->gemini_test_result = [
                    'success' => false,
                    'message' => 'Kunci API kosong. Silakan masukkan kunci API Gemini atau kunci API Vision.'
                ];
                $this->testing_gemini = false;
                return;
            }

            // Test with a simple text generation request
            $response = \Illuminate\Support\Facades\Http::timeout(10)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->ai_gemini_model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => 'Say "Hello" if you can read this.',
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    $this->gemini_test_result = [
                        'success' => true,
                        'message' => 'Koneksi berhasil! Model "' . $this->ai_gemini_model . '" tersedia dan berfungsi.'
                    ];
                } else {
                    $this->gemini_test_result = [
                        'success' => false,
                        'message' => 'Koneksi berhasil tetapi format respons tidak terduga.'
                    ];
                }
            } else {
                $error = $response->json();
                $errorMessage = $error['error']['message'] ?? 'Koneksi gagal. Silakan periksa kunci API dan pemilihan model Anda.';
                
                // Provide helpful error messages
                if (str_contains($errorMessage, 'API key not valid')) {
                    $errorMessage = 'Kunci API tidak valid. Silakan periksa kunci API Gemini Anda.';
                } elseif (str_contains($errorMessage, 'not found') || str_contains($errorMessage, '404')) {
                    $errorMessage = 'Model "' . $this->ai_gemini_model . '" tidak ditemukan. Silakan pilih model yang berbeda.';
                } elseif (str_contains($errorMessage, 'PERMISSION_DENIED')) {
                    $errorMessage = 'Izin ditolak. Pastikan kunci API memiliki akses Gemini API.';
                } elseif (str_contains($errorMessage, 'quota')) {
                    $errorMessage = 'Kuota API terlampaui. Silakan periksa penagihan Google Cloud Anda.';
                }
                
                $this->gemini_test_result = [
                    'success' => false,
                    'message' => $errorMessage
                ];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->gemini_test_result = [
                'success' => false,
                'message' => 'Koneksi timeout. Silakan periksa koneksi internet Anda.'
            ];
        } catch (\Exception $e) {
            $this->gemini_test_result = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        } finally {
            $this->testing_gemini = false;
        }
    }

    public function save()
    {
        Setting::set('ai_api_key', $this->ai_api_key);
        Setting::set('ai_gemini_api_key', $this->ai_gemini_api_key);
        Setting::set('ai_gemini_model', $this->ai_gemini_model);
        Setting::set('ai_prompt_transfer', $this->ai_prompt_transfer);
        Setting::set('ai_prompt_bill', $this->ai_prompt_bill);
        Setting::set('ai_fallback_prompt', $this->ai_fallback_prompt);

        session()->flash('success', 'Pengaturan AI berhasil disimpan!');
    }
    
    public function layout(): string
    {
        return 'components.layouts.app';
    }
    
    public function with(): array
    {
        return ['title' => 'Pengaturan AI'];
    }
}; ?>

<div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">Pengaturan AI</h1>
        </div>

        @if (session('success'))
            <div class="rounded-md bg-green-50 p-4 text-green-800 dark:bg-green-900 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        <form wire:submit="save" class="flex flex-col gap-6">
            <x-ui.card title="Konfigurasi API">
                <div class="space-y-4">
                    <!-- Vision API Key -->
                    <div>
                        <x-ui.input 
                            label="Kunci API Google Vision" 
                            name="ai_api_key" 
                            type="password"
                            wire:model.live.debounce.1000ms="ai_api_key"
                            placeholder="Masukkan kunci API Google Vision Anda"
                        />
                        <div class="mt-2 flex items-center gap-2">
                            <x-ui.button 
                                type="button" 
                                variant="outline" 
                                wire:click="testVisionConnection"
                                :disabled="$testing_vision"
                                class="text-sm"
                            >
                                @if($testing_vision)
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Menguji...
                                @else
                                    Uji Koneksi
                                @endif
                            </x-ui.button>
                        </div>
                        @if($vision_test_result)
                            <div class="mt-2 p-3 rounded-md text-sm {{ $vision_test_result['success'] ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-200' : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-200' }}">
                                <div class="flex items-start gap-2">
                                    @if($vision_test_result['success'])
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span>{{ $vision_test_result['message'] }}</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Gemini API Key -->
                    <div>
                        <x-ui.input 
                            label="Kunci API Google Gemini (Opsional)" 
                            name="ai_gemini_api_key" 
                            type="password"
                            wire:model.live.debounce.1000ms="ai_gemini_api_key"
                            placeholder="Kosongkan untuk menggunakan kunci API Vision"
                        />
                        <p class="mt-1 text-xs text-neutral-500">Jika kosong, akan menggunakan kunci API Vision</p>
                        <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">Mengubah kunci API akan memuat ulang daftar model secara otomatis (setelah 1 detik)</p>
                    </div>

                    <!-- Gemini Model Selection -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                Model Gemini
                            </label>
                            <x-ui.button 
                                type="button" 
                                variant="outline" 
                                wire:click="refreshModels"
                                :disabled="$loading_models"
                                class="text-xs"
                            >
                                @if($loading_models)
                                    <svg class="animate-spin -ml-1 mr-1 h-3 w-3" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Memuat...
                                @else
                                    <svg class="-ml-1 mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    Refresh
                                @endif
                            </x-ui.button>
                        </div>
                        <x-ui.select 
                            name="ai_gemini_model" 
                            wire:model="ai_gemini_model"
                            :disabled="$loading_models"
                        >
                            @if($loading_models)
                                <option value="">Memuat daftar model...</option>
                            @elseif(empty($gemini_models))
                                <option value="">Tidak ada model tersedia</option>
                            @else
                                @foreach($gemini_models as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            @endif
                        </x-ui.select>
                        @if($models_error)
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                {{ $models_error }}
                            </p>
                        @elseif(!$loading_models && !empty($gemini_models))
                            <p class="mt-1 text-xs text-neutral-500">
                                {{ count($gemini_models) }} model tersedia dari Google API
                            </p>
                        @endif
                        <div class="mt-2 flex items-center gap-2">
                            <x-ui.button 
                                type="button" 
                                variant="outline" 
                                wire:click="testGeminiConnection"
                                :disabled="$testing_gemini || $loading_models"
                                class="text-sm"
                            >
                                @if($testing_gemini)
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Menguji...
                                @else
                                    Uji Koneksi Gemini
                                @endif
                            </x-ui.button>
                        </div>
                        @if($gemini_test_result)
                            <div class="mt-2 p-3 rounded-md text-sm {{ $gemini_test_result['success'] ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-200' : 'bg-red-50 text-red-800 dark:bg-red-900/20 dark:text-red-200' }}">
                                <div class="flex items-start gap-2">
                                    @if($gemini_test_result['success'])
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    @endif
                                    <span>{{ $gemini_test_result['message'] }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Prompt Kustom">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                        Prompt Bukti Transfer
                    </label>
                    <textarea 
                        name="ai_prompt_transfer"
                        wire:model="ai_prompt_transfer"
                        rows="4"
                        class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-2 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:border-neutral-500 dark:focus:ring-neutral-500"
                        placeholder="Masukkan prompt kustom untuk mengekstrak jumlah pembayaran dari bukti transfer..."
                    ></textarea>
                    <p class="mt-1 text-xs text-neutral-500">Kosongkan untuk menggunakan prompt default</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                        Prompt Bukti Tagihan
                    </label>
                    <textarea 
                        name="ai_prompt_bill"
                        wire:model="ai_prompt_bill"
                        rows="6"
                        class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-2 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:border-neutral-500 dark:focus:ring-neutral-500"
                        placeholder="Masukkan prompt kustom untuk mengekstrak jumlah tagihan (menangani teks tulisan tangan dan beberapa pembayaran)..."
                    ></textarea>
                    <p class="mt-1 text-xs text-neutral-500">Kosongkan untuk menggunakan prompt default. Prompt ini harus menangani jumlah tulisan tangan dan beberapa entri pembayaran.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                        Prompt Cadangan
                    </label>
                    <textarea 
                        name="ai_fallback_prompt"
                        wire:model="ai_fallback_prompt"
                        rows="3"
                        class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 placeholder-neutral-400 focus:border-neutral-500 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-2 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:border-neutral-500 dark:focus:ring-neutral-500"
                        placeholder="Masukkan prompt cadangan yang digunakan ketika prompt kustom gagal..."
                    ></textarea>
                    <p class="mt-1 text-xs text-neutral-500">Kosongkan untuk menggunakan prompt cadangan default</p>
                </div>
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button type="submit" variant="primary">
                    Simpan Pengaturan
                </x-ui.button>
            </div>
        </form>
</div>

