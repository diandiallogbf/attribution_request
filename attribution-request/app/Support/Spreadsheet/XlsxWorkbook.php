<?php

namespace App\Support\Spreadsheet;

use App\ValueObjects\MoneyGnf;
use DateTimeInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Classeur Excel (.xlsx) prêt pour la comptabilité : une feuille par tableau, en-tête figé,
 * montants en vraies cellules numériques (sommables dans Excel), dates en vraies dates.
 * Écrit en flux : pas de fichier temporaire, pas de file d'attente.
 */
final class XlsxWorkbook
{
    private const MONEY_FORMAT = '#,##0" GNF"';

    private const DATE_FORMAT = 'dd/mm/yyyy';

    /** @var list<array{name: string, headers: list<string>, rows: iterable<array<int, mixed>>, total: bool}> */
    private array $sheets = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows  valeurs scalaires, MoneyGnf, dates ou null
     * @param  bool  $total  ajoute une ligne « Total » sommant les colonnes de montants
     */
    public function sheet(string $name, array $headers, iterable $rows, bool $total = false): self
    {
        // Excel limite le nom d'onglet à 31 caractères et interdit : \ / ? * [ ]
        $this->sheets[] = [
            'name' => mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $name), 0, 31),
            'headers' => $headers,
            'rows' => $rows,
            'total' => $total,
        ];

        return $this;
    }

    public function download(string $fileName): StreamedResponse
    {
        return response()->streamDownload(
            fn () => $this->writeTo('php://output'),
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function writeTo(string $path): void
    {
        $options = new Options;
        $options->setColumnWidth(18, ...range(1, 30));

        $writer = new Writer($options);
        $writer->openToFile($path);

        $header = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor('1C355E');
        $totalStyle = (new Style)->setFontBold()->setBackgroundColor('E8ECF3');
        $money = (new Style)->setFormat(self::MONEY_FORMAT);
        $date = (new Style)->setFormat(self::DATE_FORMAT);

        foreach ($this->sheets as $index => $sheet) {
            $current = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $current->setName($sheet['name']);
            $current->setSheetView((new SheetView)->setFreezeRow(2));

            $writer->addRow(Row::fromValues($sheet['headers'], $header));

            $totals = [];

            foreach ($sheet['rows'] as $row) {
                $values = [];
                $styles = [];

                foreach (array_values($row) as $column => $value) {
                    if ($value instanceof MoneyGnf) {
                        $totals[$column] = ($totals[$column] ?? 0) + $value->amount;
                        $styles[$column] = $money;
                        $value = $value->amount;
                    } elseif ($value instanceof DateTimeInterface) {
                        $styles[$column] = $date;
                    }

                    $values[] = $value;
                }

                $writer->addRow(Row::fromValuesWithStyles($values, null, $styles));
            }

            if ($sheet['total'] && $totals !== []) {
                $values = array_fill(0, count($sheet['headers']), null);
                $values[0] = 'Total';
                foreach ($totals as $column => $sum) {
                    $values[$column] = $sum;
                }

                $writer->addRow(Row::fromValuesWithStyles($values, $totalStyle, array_map(fn () => (clone $money)->setFontBold()->setBackgroundColor('E8ECF3'), $totals)));
            }
        }

        $writer->close();
    }
}
