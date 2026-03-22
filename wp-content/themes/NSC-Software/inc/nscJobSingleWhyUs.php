<?php

declare(strict_types=1);

/**
 * Job single — default “Why us?” bullets when theme options repeater is empty.
 *
 * ACF load_value can miss some option storage IDs (e.g. multilingual options), so we
 * merge here for the front-end template as well.
 *
 * @see single-job.php templates/single-job.twig
 */

/**
 * @return list<array{line: string}>
 */
function nsc_job_single_default_why_us_items(): array
{
    return [
        ['line' => __('Diversity of domains and challenging projects', 'NscSoftware')],
        ['line' => __('Remote-friendly collaboration across regions', 'NscSoftware')],
        ['line' => __('Mature engineering culture and clear processes', 'NscSoftware')],
        ['line' => __('Focus on quality, security, and sustainable delivery', 'NscSoftware')],
        ['line' => __('Support for learning and professional growth', 'NscSoftware')],
    ];
}

/**
 * @param array<string, mixed> $jobSingle Options::getTranslatable('NSCJobSingle')
 *
 * @return array<string, mixed>
 */
function nsc_job_single_merge_why_us_items(array $jobSingle): array
{
    $raw = $jobSingle['jobSidebarWhyUsItems'] ?? null;
    $filled = [];
    if (is_array($raw)) {
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $line = isset($row['line']) ? trim((string) $row['line']) : '';
            if ($line === '') {
                continue;
            }
            $filled[] = ['line' => $line];
        }
    }
    if ($filled !== []) {
        $jobSingle['jobSidebarWhyUsItems'] = $filled;

        return $jobSingle;
    }
    $jobSingle['jobSidebarWhyUsItems'] = nsc_job_single_default_why_us_items();

    return $jobSingle;
}
