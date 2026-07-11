<div>
    <x-filament-panels::page>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 font-sans antialiased text-right" dir="rtl">
            
            <div class="lg:col-span-1 bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 space-y-4">
                <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800 pb-3">
                    <span class="text-xl">📋</span>
                    <h3 class="text-xs font-black text-gray-800 dark:text-gray-200">پارامترهای ورودی متقاضی</h3>
                </div>
                
                <form wire:submit.prevent="processAnalysis" class="space-y-6">
                    {{ $this->form }}
                    
                    <x-filament::button type="submit" size="lg" icon="heroicon-m-sparkles" class="w-full bg-amber-600 hover:bg-amber-500 shadow-md shadow-amber-500/20 transition-all duration-300">
                        🚀 پردازش و تدوین استراتژی مذاکره
                    </x-filament::button>
                </form>
            </div>

            <div class="lg:col-span-2 flex flex-col space-y-4">
                <div class="flex-1 bg-white dark:bg-gray-900 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 min-h-[550px] flex flex-col">
                    
                    <div class="flex justify-between items-center border-b border-gray-100 dark:border-gray-800 pb-4 mb-5">
                        <div class="flex items-center gap-2">
                            <span class="text-amber-500 animate-pulse text-lg">☀️</span>
                            <h3 class="text-xs font-black text-gray-900 dark:text-gray-100">
                                خروجی بیانیه، مدل مالی و کلوزینگ استراتژیک okSolar
                            </h3>
                        </div>
                        @if($ai_output)
                            <span class="bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200/50 dark:border-amber-800/40 px-3 py-1 rounded-full font-mono text-[9px] font-bold tracking-wider">
                                Elite Strategy Core
                            </span>
                        @endif
                    </div>

                    @if($ai_output)
                        <div class="flex-1 overflow-y-auto bg-gray-50/50 dark:bg-gray-950/40 p-6 rounded-xl border border-gray-100 dark:border-gray-800/60 select-text">
                            
                            <div class="text-gray-800 dark:text-gray-300 text-xs leading-8 space-y-6 text-justify tracking-wide
                                        [&>h2]:text-sm [&>h2]:font-black [&>h2]:text-amber-600 [&>h2]:dark:text-amber-400 [&>h2]:border-r-4 [&>h2]:border-amber-600 [&>h2]:pr-3 [&>h2]:mt-8 [&>h2]:mb-4 [&>h2]:bg-amber-50/40 [&>h2]:dark:bg-amber-950/20 [&>h2]:py-2
                                        [&>h3]:text-xs [&>h3]:font-bold [&>h3]:text-gray-900 [&>h3]:dark:text-white [&>h3]:mt-5 [&>h3]:mb-2
                                        [&>p]:mb-4 [&>p]:leading-7
                                        [&>ul]:list-disc [&>ul]:pr-6 [&>ul]:space-y-2 [&>ul]:mb-4
                                        [&>ol]:list-decimal [&>ol]:pr-6 [&>ol]:space-y-2 [&>ol]:mb-4
                                        [&>blockquote]:border-r-4 [&>blockquote]:border-amber-500 [&>blockquote]:bg-amber-50/20 [&>blockquote]:dark:bg-gray-900 [&>blockquote]:p-4 [&>blockquote]:rounded-l-xl [&>blockquote]:my-5 [&>blockquote]:text-gray-700 [&>blockquote]:dark:text-gray-300 [&>blockquote]:font-medium [&>blockquote]:italic">
                                {!! $ai_output !!}
                            </div>
                            
                        </div>
                    @else
                        <div class="flex-1 flex flex-col items-center justify-center text-center p-12 border-2 border-dashed border-gray-100 dark:border-gray-800 rounded-xl bg-gray-50/50 dark:bg-gray-950/20">
                            <div class="w-16 h-16 bg-amber-50 dark:bg-amber-950/30 rounded-full flex items-center justify-center mb-4 shadow-inner">
                                <span class="text-2xl">💡</span>
                            </div>
                            <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">آماده پردازش داده‌ها</h4>
                            <p class="text-[11px] text-gray-400 dark:text-gray-500 max-w-sm leading-5">
                                مشخصات سناریو و جزئیات پروژه تجاری یا خانگی را در سمت راست وارد کنید تا نقشه راه مذاکره ممیزی شده صادر شود.
                            </p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </x-filament-panels::page>
</div>