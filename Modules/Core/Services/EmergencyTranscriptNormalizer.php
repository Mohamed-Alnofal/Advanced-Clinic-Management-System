<?php

namespace Modules\Core\Services;

class EmergencyTranscriptNormalizer
{
    /** @var array<string, string> Built-in fallbacks if synonym file is unavailable. */
    private const FALLBACK_REPLACEMENTS = [
        'ما بقدر' => 'لا أستطيع',
        'ما عم' => 'لا',
        'عم يختنق' => 'اختناق',
        'ذبحه قلب' => 'ألم صدر',
        'ذبح قلب' => 'ألم صدر',
        'الم صدر' => 'ألم صدر',
        'معي ذبحه' => 'ألم صدر',
        'مغمى عليه' => 'فقدان وعي',
        'ما عم بحكي' => 'كلام مشوش',
        'ما بقدر احكي' => 'كلام مشوش',
        'وجهي مائل' => 'ميلان الوجه',
        'انقطاع نفس' => 'انقطاع تنفس',
        'لا اقدر اتنفس' => 'لا أستطيع التنفس',
        'نزف' => 'نزيف',
        'انحرق' => 'حرق',
        'غرقان' => 'غرق',
    ];

    public function __construct(
        private EmergencySynonymExpander $synonymExpander,
    ) {}

    public function normalize(string $transcript): string
    {
        try {
            return $this->synonymExpander->applyReplacements($transcript);
        } catch (\Throwable) {
            $text = trim(preg_replace('/\s+/u', ' ', $transcript) ?? $transcript);
            $text = mb_strtolower($text);

            foreach (self::FALLBACK_REPLACEMENTS as $search => $replace) {
                $text = str_replace(mb_strtolower($search), $replace, $text);
            }

            return trim($text);
        }
    }
}
