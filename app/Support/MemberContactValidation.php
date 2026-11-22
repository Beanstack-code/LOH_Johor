<?php

namespace App\Support;

/**
 * Mirrors the Flutter registration rules in lib/utils/input_validators.dart and the IC/phone formatters.
 */
final class MemberContactValidation
{
    /**
     * Malaysian IC: digits-only core max 12; hyphens while typing (Flutter MalaysianIcInputFormatter);
     * complete value matches normalizeMalaysianIcForApi: xxxxxx-xx-xxxx.
     */
    public static function normalizeMalaysianIc(?string $input): string
    {
        if ($input === null || trim($input) === '') {
            return '';
        }

        $trimmed = trim($input);
        if (preg_match('/^\d{6}-\d{2}-\d{4}$/', $trimmed)) {
            return $trimmed;
        }

        $digits = preg_replace('/\D/', '', $trimmed) ?? '';
        if (strlen($digits) > 12) {
            $digits = substr($digits, 0, 12);
        }
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) <= 6) {
            return $digits;
        }
        if (strlen($digits) <= 8) {
            return substr($digits, 0, 6).'-'.substr($digits, 6);
        }

        return substr($digits, 0, 6).'-'.substr($digits, 6, 2).'-'.substr($digits, 8, 4);
    }

    /**
     * Same rule as malaysianIc in the app (optional empty allowed for admin).
     *
     * @return non-empty-string|null Error message, or null if valid (including empty).
     */
    public static function icNumberError(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) !== 12) {
            return 'IC must be 12 digits in format xxxxxx-xx-xxxx';
        }

        $normalized = self::normalizeMalaysianIc($value);
        if (! preg_match('/^\d{6}-\d{2}-\d{4}$/', $normalized)) {
            return 'IC must be 12 digits in format xxxxxx-xx-xxxx';
        }

        return null;
    }

    /**
     * Same pipeline as registration: domestic to +60/+65, then compact like normalizePhoneForApi.
     */
    public static function normalizeMalaysianPhone(?string $input): string
    {
        if ($input === null || trim($input) === '') {
            return '';
        }

        $t = trim($input);

        if (preg_match('/^\+(60|65)/', $t, $m)) {
            $prefix = $m[0];
            $rest = substr($t, strlen($prefix));
            $digits = preg_replace('/\D/', '', $rest) ?? '';
            $digits = self::stripOneLeadingZeroFromDigitRun($digits);

            return $prefix.$digits;
        }

        $digits = preg_replace('/\D/', '', $t) ?? '';
        if ($digits === '') {
            return $t;
        }

        if (preg_match('/^0([1-9]\d{8,9})$/', $digits, $m)) {
            return '+60'.$m[1];
        }

        if (preg_match('/^60([1-9]\d{8,9})$/', $digits, $m)) {
            return '+60'.$m[1];
        }

        if (preg_match('/^65(\d{8})$/', $digits, $m)) {
            return '+65'.$m[1];
        }

        return $t;
    }

    /**
     * Same pattern as PhoneLocalDigitsInputFormatter: cap digits, insert XX-XXXX-XXXX groups.
     */
    public static function formatPhoneLocalDigits(string $input, int $maxDigits): string
    {
        $rawDigits = preg_replace('/\D/', '', $input) ?? '';
        $digits = strlen($rawDigits) > $maxDigits ? substr($rawDigits, 0, $maxDigits) : $rawDigits;
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) <= 2) {
            return $digits;
        }
        if (strlen($digits) <= 6) {
            return substr($digits, 0, 2).'-'.substr($digits, 2);
        }

        return substr($digits, 0, 2).'-'.substr($digits, 2, 4).'-'.substr($digits, 6);
    }

    /**
     * Same as normalizePhoneForApi(prefix, localPart): compact E.164 digits only.
     */
    public static function composePhoneFromPrefixAndLocal(string $prefix, string $local): string
    {
        $prefix = in_array($prefix, ['+60', '+65'], true) ? $prefix : '+60';
        $digits = preg_replace('/\D/', '', $local) ?? '';
        if ($digits === '') {
            return '';
        }
        $digits = self::stripOneLeadingZeroFromDigitRun($digits);

        return $prefix.$digits;
    }

    /**
     * @return array{0: string, 1: string} [prefix, formattedLocal]
     */
    public static function splitStoredPhoneForForm(?string $stored): array
    {
        $normalized = self::normalizeMalaysianPhone((string) $stored);
        if ($normalized === '' || ! preg_match('/^\+(60|65)/', $normalized)) {
            return ['+60', ''];
        }
        if (str_starts_with($normalized, '+65')) {
            $digits = substr($normalized, 3);

            return ['+65', self::formatPhoneLocalDigits($digits, 8)];
        }
        $digits = substr($normalized, 3);

        return ['+60', self::formatPhoneLocalDigits($digits, 10)];
    }

    /**
     * Same checks as phoneLocal in the app (optional empty for admin).
     *
     * @return non-empty-string|null Error message, or null if valid (including empty).
     */
    public static function phoneError(mixed $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        $trimmed = trim($phone);
        if (! preg_match('/^\+(60|65)/', $trimmed, $m)) {
            return 'The phone must use country code +60 or +65.';
        }

        $prefix = $m[0];
        $rest = substr($trimmed, strlen($prefix));
        $digits = preg_replace('/\D/', '', $rest) ?? '';
        if ($digits === '') {
            return 'Enter a valid phone number';
        }

        $digits = self::stripOneLeadingZeroFromDigitRun($digits);

        if ($prefix === '+60') {
            if (strlen($digits) < 9 || strlen($digits) > 10) {
                return 'Malaysia phone number must be 9 or 10 digits after +60 (you can use - separators)';
            }
        } elseif (strlen($digits) !== 8) {
            return 'Singapore phone number must be 8 digits after +65';
        }

        return null;
    }

    private static function stripOneLeadingZeroFromDigitRun(string $digits): string
    {
        if ($digits !== '' && $digits[0] === '0') {
            return substr($digits, 1);
        }

        return $digits;
    }
}
