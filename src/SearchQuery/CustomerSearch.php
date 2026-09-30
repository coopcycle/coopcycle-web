<?php

namespace AppBundle\SearchQuery;

/**
 * The bits of "search for a customer" shared by the two places that do it:
 * the "customer:" filter on /admin/orders (AppBundle\SearchQuery\Orders) and
 * the autocomplete backing it (OrdersAutocompleteController::customer).
 *
 * Both look a customer up by email, name or phone number, and have to agree
 * on what those mean in DQL - otherwise picking a suggestion could return
 * orders the suggestion itself wasn't found by.
 */
final class CustomerSearch
{
    // Below this, a "phone number" is too short to be worth searching on -
    // see phoneNeedle().
    private const PHONE_MIN_DIGITS = 4;

    private function __construct()
    {
    }

    /**
     * The customer's name as one string, so a query can be matched against
     * "Jane Doe" and not only against either half of it.
     *
     * COALESCE because Postgres concatenating a NULL gives NULL, which would
     * drop every customer missing a first or last name.
     */
    public static function fullNameExpr(string $alias): string
    {
        return sprintf("TRIM(CONCAT(COALESCE(%s.firstName, ''), ' ', COALESCE(%s.lastName, '')))", $alias, $alias);
    }

    /**
     * The stored phone number reduced to bare digits, so it can be compared
     * to a query normalized the same way - see phoneNeedle().
     */
    public static function phoneDigitsExpr(string $alias): string
    {
        return sprintf("REGEXP_REPLACE(COALESCE(%s.phoneNumber, ''), '[^0-9]', '', 'g')", $alias);
    }

    /**
     * The digits to look for inside a stored phone number, or null when $q
     * isn't phone-number-ish enough to bother.
     *
     * Numbers are stored in E.164 ("+33612345678"), but nobody types them
     * that way - an admin reads "06 12 34 56 78" off an order. So both sides
     * are reduced to bare digits and compared as a substring, which makes
     * the local, international and half-typed forms of one number all match
     * it: leading zeros are dropped (a national trunk prefix that the E.164
     * form doesn't have), and what's left ("612345678") is contained in the
     * stored digits ("33612345678") whichever way the number was entered.
     *
     * Deliberately not fuzzy, unlike the email and the name: one digit off
     * is a different phone number, not a typo worth forgiving.
     *
     * The 4-digit floor keeps a half-typed number from matching most of the
     * table before it's specific enough to mean anything.
     */
    public static function phoneNeedle(string $q): ?string
    {
        // Anything other than digits and the punctuation phone numbers are
        // written with means this isn't one - "jane.doe@example.com" has
        // digits in it too, and has no business matching a phone number.
        if (1 !== preg_match('/^[+()\/.\- \d]+$/', $q)) {
            return null;
        }

        $digits = ltrim(preg_replace('/\D/', '', $q), '0');

        return strlen($digits) >= self::PHONE_MIN_DIGITS ? $digits : null;
    }

    /**
     * The GIN trigram indexes that make the above expressions cheap, as
     * "<name> => <definition>" - created by Version20260930090000, and by
     * OrdersAutocompleteControllerTest, whose test database is built from
     * the schema rather than by running migrations.
     *
     * These spell in raw SQL what fullNameExpr() and phoneDigitsExpr() spell
     * in DQL. The two forms are necessarily different strings - DQL's CONCAT
     * becomes "||", its TRIM becomes "TRIM(BOTH FROM ...)" - but they have to
     * stay the same *expression*, because Postgres matches an expression
     * index to a query by comparing parsed expressions. If they drift apart
     * the index is silently ignored and only the query plan shows it, which
     * is why there is a test asserting the plan.
     */
    public static function indexDefinitions(): array
    {
        return [
            'idx_customer_email_trgm' =>
                'sylius_customer USING gin (email_canonical gin_trgm_ops)',
            'idx_customer_full_name_trgm' =>
                "sylius_customer USING gin ((TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))) gin_trgm_ops)",
            'idx_customer_phone_digits_trgm' =>
                "sylius_customer USING gin ((REGEXP_REPLACE(COALESCE(phone_number, ''), '[^0-9]', '', 'g')) gin_trgm_ops)",
        ];
    }
}
