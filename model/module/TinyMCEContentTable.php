<?php

namespace MiMFa\Module;

use MiMFa\Library\Struct;

class TinyMCEContentTable extends Table
{
    public function FilterItems($items)
    {
        if (!$this->Filter && !$this->FilterGraph)
            return $items;

        $filter = self::NormalizeSearchValue((string)$this->Filter);
        if ($filter)
            $this->FilterPattern = \_::$Back->Query->ConvertToPattern($filter);

        return filter(
            $items,
            fn($value) => (!$this->FilterGraph || graphAnd($value, $this->FilterGraph))
                && (!$filter || take(
                    $value,
                    fn($cell) => is_string($cell)
                        && preg_match($this->FilterPattern, self::NormalizeSearchValue($cell))
                ))
        );
    }

    public static function NormalizeSearchValue(string $value): string
    {
        $value = strtr($value, [
            "\u{064A}" => "\u{06CC}",
            "\u{0649}" => "\u{06CC}",
            "\u{0643}" => "\u{06A9}",
            "\u{0623}" => "\u{0627}",
            "\u{0625}" => "\u{0627}",
            "\u{0624}" => "\u{0648}",
            "\u{06C0}" => "\u{0647}",
            "\u{200C}" => ' '
        ]);

        return preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $value) ?? $value;
    }

    public function GetScript()
    {
        $mainClass = json_encode($this->MainClass, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return parent::GetScript() . Struct::Script(<<<JS
        (() => {
            const normalize = (value) => String(value ?? '')
                .replace(/[\u064A\u0649]/g, '\u06CC')
                .replace(/\u0643/g, '\u06A9')
                .replace(/[\u0623\u0625]/g, '\u0627')
                .replace(/\u0624/g, '\u0648')
                .replace(/\u06C0/g, '\u0647')
                .replace(/\u200C/g, ' ')
                .replace(/[\u064B-\u065F\u0670]/g, '');

            const connect = () => {
                const root = document.querySelector('.' + $mainClass);
                const element = root?.querySelector('table');
                const input = root?.querySelector('.dataTables_filter input[type="search"]');
                if (!element || !input || !window.jQuery?.fn?.DataTable || input.dataset.aseqSearchNormalizer === '1')
                    return;

                input.dataset.aseqSearchNormalizer = '1';
                const table = window.jQuery(element).DataTable();
                input.addEventListener('input', () => {
                    const normalized = normalize(input.value);
                    if (normalized !== input.value)
                        input.value = normalized;
                    table.search(normalized).draw();
                });
            };

            if (document.readyState === 'loading')
                document.addEventListener('DOMContentLoaded', connect, { once: true });
            else
                window.setTimeout(connect);
        })();
        JS);
    }
}
