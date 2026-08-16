<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MondialRelayClient
{
    private const ENDPOINT = 'https://api.mondialrelay.com/Web_Services.asmx';

    private const SOAP_ACTION = 'http://www.mondialrelay.fr/webservice/WSI2_RecherchePointRelaisHoraires';

    private const DAYS = [
        'Horaires_Lundi' => 'Lun',
        'Horaires_Mardi' => 'Mar',
        'Horaires_Mercredi' => 'Mer',
        'Horaires_Jeudi' => 'Jeu',
        'Horaires_Vendredi' => 'Ven',
        'Horaires_Samedi' => 'Sam',
        'Horaires_Dimanche' => 'Dim',
    ];

    /**
     * Relay points near a postal code, nearest first (as ranked by Mondial
     * Relay), with opening hours. Empty when credentials are missing or the
     * call fails.
     *
     * @return array<int, array{num: string, name: string, line1: string, postal_code: string, city: string, country: string, hours: ?string}>
     */
    public function searchByPostalCode(string $postalCode, string $country = 'FR'): array
    {
        $enseigne = config('services.mondial_relay.enseigne');
        $privateKey = config('services.mondial_relay.private_key');

        if (blank($enseigne) || blank($privateKey) || blank($postalCode)) {
            return [];
        }

        $params = [
            'Enseigne' => $enseigne,
            'Pays' => strtoupper($country),
            'Ville' => '',
            'CP' => $postalCode,
            'Taille' => '',
            'Poids' => '',
            'Action' => '',
        ];

        $security = strtoupper(md5(implode('', $params).$privateKey));

        $body = '<?xml version="1.0" encoding="utf-8"?>'
            .'<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            .'<soap:Body>'
            .'<WSI2_RecherchePointRelaisHoraires xmlns="http://www.mondialrelay.fr/webservice/">'
            .'<Enseigne>'.e($params['Enseigne']).'</Enseigne>'
            .'<Pays>'.e($params['Pays']).'</Pays>'
            .'<Ville>'.e($params['Ville']).'</Ville>'
            .'<CP>'.e($params['CP']).'</CP>'
            .'<Taille>'.e($params['Taille']).'</Taille>'
            .'<Poids>'.e($params['Poids']).'</Poids>'
            .'<Action>'.e($params['Action']).'</Action>'
            .'<Security>'.$security.'</Security>'
            .'</WSI2_RecherchePointRelaisHoraires>'
            .'</soap:Body>'
            .'</soap:Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '"'.self::SOAP_ACTION.'"',
            ])->timeout(8)->send('POST', self::ENDPOINT, ['body' => $body]);
        } catch (\Throwable $exception) {
            Log::warning('Mondial Relay search failed', ['message' => $exception->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->parse($response->body());
    }

    /**
     * @return array<int, array{num: string, name: string, line1: string, postal_code: string, city: string, country: string, hours: ?string}>
     */
    private function parse(string $body): array
    {
        $clean = preg_replace('/(<\/?)[a-zA-Z0-9]+:/', '$1', $body);
        $xml = @simplexml_load_string($clean ?? '');

        if ($xml === false) {
            return [];
        }

        $result = $xml->Body->WSI2_RecherchePointRelaisHorairesResponse->WSI2_RecherchePointRelaisHorairesResult ?? null;

        if ($result === null || (string) $result->STAT !== '0') {
            return [];
        }

        $points = [];

        foreach ($result->ListePR->children() as $node) {
            $num = trim((string) $node->Num);

            if ($num === '') {
                continue;
            }

            $points[] = [
                'num' => $num,
                'name' => trim((string) $node->LgAdr1),
                'line1' => trim((string) $node->LgAdr3),
                'postal_code' => trim((string) $node->CP),
                'city' => trim((string) $node->Ville),
                'country' => trim((string) $node->Pays),
                'hours' => $this->formatHours($node),
            ];
        }

        return $points;
    }

    private function formatHours(\SimpleXMLElement $node): ?string
    {
        $days = [];

        foreach (self::DAYS as $field => $label) {
            $day = $node->{$field} ?? null;
            $rangeText = null;

            if ($day !== null) {
                $slots = array_values(array_map('trim', (array) $day->string));
                $ranges = [];

                for ($i = 0; $i + 1 < count($slots); $i += 2) {
                    if ($slots[$i] === '' || $slots[$i + 1] === '') {
                        continue;
                    }

                    $ranges[] = $this->formatTime($slots[$i]).'-'.$this->formatTime($slots[$i + 1]);
                }

                $rangeText = $ranges === [] ? null : implode(', ', $ranges);
            }

            $days[] = ['label' => $label, 'range' => $rangeText];
        }

        return $this->condenseDays($days);
    }

    /**
     * Groups consecutive days sharing the same opening hours into a single
     * "Lun-Ven HH:MM-HH:MM" line instead of repeating it once per day.
     *
     * @param  array<int, array{label: string, range: ?string}>  $days
     */
    private function condenseDays(array $days): ?string
    {
        $groups = [];

        foreach ($days as $day) {
            if ($day['range'] === null) {
                continue;
            }

            $last = $groups === [] ? null : $groups[array_key_last($groups)];

            if ($last !== null && $last['range'] === $day['range'] && $last['lastLabel'] === $this->previousDayLabel($day['label'])) {
                $groups[array_key_last($groups)]['end'] = $day['label'];
                $groups[array_key_last($groups)]['lastLabel'] = $day['label'];

                continue;
            }

            $groups[] = ['start' => $day['label'], 'end' => $day['label'], 'lastLabel' => $day['label'], 'range' => $day['range']];
        }

        if ($groups === []) {
            return null;
        }

        return implode(' · ', array_map(
            fn (array $g): string => ($g['start'] === $g['end'] ? $g['start'] : $g['start'].'-'.$g['end']).' '.$g['range'],
            $groups,
        ));
    }

    private function previousDayLabel(string $label): ?string
    {
        $labels = array_values(self::DAYS);
        $index = array_search($label, $labels, true);

        return $index !== false && $index > 0 ? $labels[$index - 1] : null;
    }

    private function formatTime(string $value): string
    {
        $value = str_pad($value, 4, '0', STR_PAD_LEFT);

        return substr($value, 0, 2).':'.substr($value, 2, 2);
    }
}
