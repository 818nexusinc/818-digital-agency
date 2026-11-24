<?php
declare(strict_types=1);
namespace App;

/** The Filing Agent's check of our registry forms against what each government registry requires. */
final class FormCheck
{
    public const ISSUES = ['missing_field' => 'Missing information', 'outdated' => 'Out of date', 'wrong_format' => 'Asked the wrong way', 'fee_change' => 'Fee changed', 'document_needed' => 'Document needed', 'other' => 'Other'];

    public static function queue(string $by, ?string $slug = null, string $note = ''): int
    {
        $scope = $slug ? 'the form for "' . $slug . '"' : 'every registration and corporate form we offer (incorporations, sole proprietorships, partnerships, business names, co-operatives, annual returns, corporate changes, document orders, NUANS)';
        $brief = "Check {$scope} against the official government registries for each jurisdiction we offer, starting with New Brunswick, Nova Scotia, Prince Edward Island, Newfoundland and Labrador and Corporations Canada. "
            . 'First call our_filing_forms to see exactly what our forms ask. Then use web_search to read the official registry pages (forms, guides, online filing requirements). '
            . 'For every gap — information the registry needs that we do not collect, something we ask in the wrong format, a document the customer must provide, an outdated rule, or a changed government fee — call save_form_finding with the official page address and a concrete suggestion for our form. '
            . 'Only record what you confirmed on an official page. Do not change anything yourself. Finish with a short summary listing what you checked and what you found.'
            . ($note !== '' ? ' Owner note: ' . $note : '');
        Settings::set('filing.form_check_last_queued', now());
        return Orchestrator::enqueue('filing', 'form_check', 'Registry form check: ' . ($slug ?: 'all registry forms'), ['brief' => $brief], ['priority' => 5, 'created_by' => $by]);
    }
}
