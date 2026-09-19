<?php

namespace App\Support\Octopia;

use DOMDocument;
use DOMElement;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * An Octopia product template, read and written as the zip it is.
 *
 * The file Octopia hands out is an .xlsm: a zip of XML parts plus the macros
 * that drive its own buttons. Reading it here rather than through a
 * spreadsheet library keeps the export honest — the exported file is the
 * one Octopia gave, with rows added to a single sheet and every other byte,
 * macros included, left exactly as it was.
 *
 * The layout is the template's own, and the same in every category:
 *
 *   row 4  the label of each column, a star marking it required
 *   row 5  the field code Octopia reads: `gtin`, `title`, or a number
 *   row 6  how many values it takes (mono, multi, monoranged, ...)
 *   row 8  the constraint in words ("132 caractères max")
 *   row 10 where the seller's rows begin
 */
class TemplateFile
{
    /** Where the seller's first row goes. Rows 1 to 9 are the template's. */
    public const FIRST_DATA_ROW = 10;

    private const LABEL_ROW = 4;

    private const CODE_ROW = 5;

    private const KIND_ROW = 6;

    private const CONSTRAINT_ROW = 8;

    public function __construct(private readonly string $path)
    {
        if (! is_file($this->path)) {
            throw new RuntimeException('The Octopia template file is missing.');
        }
    }

    /**
     * Everything a page needs about the template, without opening it again.
     *
     * @return array{code: string, name: string, sheet_path: string, fields: list<array<string, mixed>>}
     */
    public function read(): array
    {
        $zip = $this->open();

        try {
            $sheetPath = $this->productSheetPath($zip);
            $strings = $this->sharedStrings($zip);
            $cells = $this->cells($zip, $sheetPath, $strings, self::CONSTRAINT_ROW);

            $code = trim((string) ($cells[self::LABEL_ROW - 3]['C'] ?? ''));
            $name = trim((string) ($cells[self::LABEL_ROW - 3]['D'] ?? ''));

            if ($code === '') {
                throw new RuntimeException('This file does not look like an Octopia template: no category code in C1.');
            }

            $options = $this->boundedLists($zip, $strings);
            $fields = [];

            foreach ($cells[self::CODE_ROW] ?? [] as $column => $fieldCode) {
                $label = trim((string) ($cells[self::LABEL_ROW][$column] ?? ''));
                $fieldCode = trim((string) $fieldCode);

                if ($fieldCode === '' || $label === '') {
                    continue;
                }

                $fields[] = [
                    'column' => $column,
                    'code' => $fieldCode,
                    // The star says Octopia refuses the sheet without it.
                    'label' => rtrim($label, '* '),
                    'required' => str_contains($label, '*'),
                    'kind' => trim((string) ($cells[self::KIND_ROW][$column] ?? '')),
                    'constraint' => trim((string) ($cells[self::CONSTRAINT_ROW][$column] ?? '')),
                    'options' => $options[$fieldCode] ?? [],
                ];
            }

            if ($fields === []) {
                throw new RuntimeException('This file does not look like an Octopia template: no columns in rows 4 and 5.');
            }

            return ['code' => $code, 'name' => $name, 'sheet_path' => $sheetPath, 'fields' => $fields];
        } finally {
            $zip->close();
        }
    }

    /**
     * A copy of the file with the given rows written from row 10 down.
     *
     * @param  list<array<string, string>>  $rows  column letter => value
     */
    public function fill(string $sheetPath, array $rows, string $destination): void
    {
        if (! copy($this->path, $destination)) {
            throw new RuntimeException('The Octopia template could not be copied.');
        }

        $zip = new ZipArchive;

        if ($zip->open($destination) !== true) {
            throw new RuntimeException('The copied Octopia template could not be opened.');
        }

        try {
            $sheet = (string) $zip->getFromName(ltrim($sheetPath, '/'));

            if ($sheet === '') {
                throw new RuntimeException('The template sheet has gone from the file.');
            }

            $zip->addFromString(ltrim($sheetPath, '/'), $this->withRows($sheet, $rows));
        } finally {
            $zip->close();
        }
    }

    /**
     * The rows, written as inline strings into the sheet.
     *
     * Inline rather than through the shared string table: the table is
     * referenced by index from every other sheet, and appending to it would
     * mean renumbering what the template already says.
     *
     * The template ships a few rows of its own below the headings, styled and
     * carrying the category's drop-down lists. Those rows are filled in place
     * rather than doubled: two rows numbered 10 is a file Excel offers to
     * repair.
     *
     * @param  list<array<string, string>>  $rows
     */
    private function withRows(string $sheetXml, array $rows): string
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        if (! $document->loadXML($sheetXml)) {
            throw new RuntimeException('The template sheet could not be read.');
        }

        $sheetData = $document->getElementsByTagNameNS('*', 'sheetData')->item(0);

        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('The template sheet has no data section.');
        }

        $namespace = (string) $sheetData->namespaceURI;
        $prefix = $sheetData->prefix !== '' ? $sheetData->prefix.':' : '';

        foreach ($rows as $index => $cells) {
            $number = self::FIRST_DATA_ROW + $index;
            $row = $this->rowElement($document, $sheetData, $namespace, $prefix, $number);

            foreach ($cells as $column => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }

                $this->writeCell($document, $row, $namespace, $prefix, $column.$number, (string) $value);
            }
        }

        return (string) $document->saveXML();
    }

    /** The row of that number, the template's own if it has one. */
    private function rowElement(DOMDocument $document, DOMElement $sheetData, string $namespace, string $prefix, int $number): DOMElement
    {
        $after = null;

        foreach ($sheetData->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->localName !== 'row') {
                continue;
            }

            $existing = (int) $node->getAttribute('r');

            if ($existing === $number) {
                return $node;
            }

            if ($existing < $number) {
                $after = $node;
            }
        }

        $row = $document->createElementNS($namespace, $prefix.'row');
        $row->setAttribute('r', (string) $number);

        // In their order: Excel expects rows and cells to climb, and repairs
        // a file where they do not.
        $this->insertInOrder($sheetData, $row, $after);

        return $row;
    }

    /**
     * One cell, replacing whatever the template had there while keeping the
     * style it gave it.
     */
    private function writeCell(DOMDocument $document, DOMElement $row, string $namespace, string $prefix, string $reference, string $value): void
    {
        $cell = null;
        $after = null;
        $column = $this->column($reference);

        foreach ($row->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->localName !== 'c') {
                continue;
            }

            $existing = $this->column($node->getAttribute('r'));

            if ($existing === $column) {
                $cell = $node;

                break;
            }

            if ($this->columnIndex($existing) < $this->columnIndex($column)) {
                $after = $node;
            }
        }

        if ($cell === null) {
            $cell = $document->createElementNS($namespace, $prefix.'c');
            $cell->setAttribute('r', $reference);
            $this->insertInOrder($row, $cell, $after);
        }

        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }

        $cell->setAttribute('t', 'inlineStr');

        $is = $document->createElementNS($namespace, $prefix.'is');
        $text = $document->createElementNS($namespace, $prefix.'t');
        $text->setAttribute('xml:space', 'preserve');
        $text->appendChild($document->createTextNode($value));
        $is->appendChild($text);
        $cell->appendChild($is);
    }

    /** Put the new element after the last one that comes before it. */
    private function insertInOrder(DOMElement $parent, DOMElement $element, ?DOMElement $after): void
    {
        if ($after === null) {
            if ($parent->firstChild !== null) {
                $parent->insertBefore($element, $parent->firstChild);

                return;
            }

            $parent->appendChild($element);

            return;
        }

        if ($after->nextSibling !== null) {
            $parent->insertBefore($element, $after->nextSibling);

            return;
        }

        $parent->appendChild($element);
    }

    /** A column letter as a number, so B comes before AA. */
    private function columnIndex(string $column): int
    {
        $index = 0;

        foreach (str_split($column) as $letter) {
            $index = $index * 26 + (ord(strtoupper($letter)) - 64);
        }

        return $index;
    }

    private function open(): ZipArchive
    {
        $zip = new ZipArchive;

        if ($zip->open($this->path) !== true) {
            throw new RuntimeException('This file is not a readable Excel template.');
        }

        return $zip;
    }

    /**
     * The sheet holding the columns: the one the workbook shows. The other,
     * hidden, holds the allowed values of the closed lists.
     */
    private function productSheetPath(ZipArchive $zip): string
    {
        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $relations = $this->relations($zip);

        foreach ($this->nodes($workbook, 'sheet') as $sheet) {
            $state = (string) $sheet['state'];
            $id = (string) $sheet->attributes('r', true)->id;

            if ($state === 'hidden' || $state === 'veryHidden' || ! isset($relations[$id])) {
                continue;
            }

            return $this->normalizePart($relations[$id]);
        }

        throw new RuntimeException('This workbook has no visible sheet to read.');
    }

    /** @return array<string, string> */
    private function relations(ZipArchive $zip): array
    {
        $relations = [];

        foreach ($this->nodes($this->xml($zip, 'xl/_rels/workbook.xml.rels'), 'Relationship') as $relation) {
            $relations[(string) $relation['Id']] = (string) $relation['Target'];
        }

        return $relations;
    }

    private function normalizePart(string $target): string
    {
        $target = ltrim($target, '/');

        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $strings = [];

        foreach ($this->nodes($this->xml($zip, 'xl/sharedStrings.xml'), 'si') as $item) {
            $text = '';

            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $part) {
                $text .= (string) $part;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * The sheet's cells up to a row, as [row number => [column letter => value]].
     *
     * @param  list<string>  $strings
     * @return array<int, array<string, string>>
     */
    private function cells(ZipArchive $zip, string $sheetPath, array $strings, int $upToRow): array
    {
        $sheet = $this->xml($zip, $sheetPath);
        $cells = [];

        foreach ($this->nodes($sheet, 'row') as $row) {
            $number = (int) $row['r'];

            if ($number > $upToRow) {
                continue;
            }

            foreach ($this->nodes($row, 'c') as $cell) {
                $value = $this->value($cell, $strings);

                if ($value !== '') {
                    $cells[$number][$this->column((string) $cell['r'])] = $value;
                }
            }
        }

        return $cells;
    }

    /** @param  list<string>  $strings */
    private function value(SimpleXMLElement $cell, array $strings): string
    {
        if ((string) $cell['t'] === 'inlineStr') {
            $text = '';

            foreach ($cell->xpath('.//*[local-name()="t"]') ?: [] as $part) {
                $text .= (string) $part;
            }

            return $text;
        }

        $raw = (string) ($this->nodes($cell, 'v')[0] ?? '');

        if ($raw === '') {
            return '';
        }

        return (string) $cell['t'] === 's' ? ($strings[(int) $raw] ?? '') : $raw;
    }

    private function column(string $reference): string
    {
        return rtrim($reference, '0123456789');
    }

    /**
     * The closed lists, read from the hidden sheet: its first row names the
     * field code, and the column beneath it holds that field's values.
     *
     * @param  list<string>  $strings
     * @return array<string, list<string>>
     */
    private function boundedLists(ZipArchive $zip, array $strings): array
    {
        $path = null;
        $relations = $this->relations($zip);

        foreach ($this->nodes($this->xml($zip, 'xl/workbook.xml'), 'sheet') as $sheet) {
            $id = (string) $sheet->attributes('r', true)->id;

            if ((string) $sheet['state'] !== '' && isset($relations[$id])) {
                $path = $this->normalizePart($relations[$id]);
            }
        }

        if ($path === null || $zip->locateName($path) === false) {
            return [];
        }

        $sheet = $this->xml($zip, $path);
        $columns = [];

        foreach ($this->nodes($sheet, 'row') as $row) {
            $number = (int) $row['r'];

            foreach ($this->nodes($row, 'c') as $cell) {
                $value = $this->value($cell, $strings);

                if ($value !== '') {
                    $columns[$this->column((string) $cell['r'])][$number] = $value;
                }
            }
        }

        $lists = [];

        foreach ($columns as $values) {
            $code = $values[1] ?? null;

            if ($code === null) {
                continue;
            }

            unset($values[1]);
            ksort($values);
            $lists[$code] = array_values($values);
        }

        return $lists;
    }

    /**
     * The descendants of an element by local name.
     *
     * Every part of the file declares a default namespace, which SimpleXML
     * will not walk by property name, and the prefixes differ from one
     * category's template to the next.
     *
     * @return list<SimpleXMLElement>
     */
    private function nodes(SimpleXMLElement $element, string $name): array
    {
        return $element->xpath('.//*[local-name()="'.$name.'"]') ?: [];
    }

    private function xml(ZipArchive $zip, string $part): SimpleXMLElement
    {
        $contents = $zip->getFromName($part);

        if ($contents === false) {
            throw new RuntimeException('The template is missing '.$part.'.');
        }

        $xml = simplexml_load_string($contents);

        if ($xml === false) {
            throw new RuntimeException('The template part '.$part.' could not be read.');
        }

        return $xml;
    }
}
