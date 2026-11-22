<?php

namespace App\Http\Requests;

use App\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
{
    protected $redirectRoute = 'admin.reports.index';

    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(array_keys(ReportService::VIEWS))],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    public function attributes(): array
    {
        return [
            'view' => 'Report type',
            'year' => 'Year',
            'month' => 'Month',
        ];
    }

    public function messages(): array
    {
        return [
            'view.in' => 'The selected report type is invalid.',
            'year.integer' => 'Year must be a number.',
            'year.between' => 'Year must be between 2000 and 2100.',
            'month.integer' => 'Month must be a number.',
            'month.between' => 'Month must be between 1 and 12.',
        ];
    }

    public function reportView(): string
    {
        return $this->validated('view') ?? ReportService::VIEW_MONTHLY;
    }

    public function year(): int
    {
        return (int) ($this->validated('year') ?? now()->year);
    }

    /**
     * The daily report always needs a month, defaulting to the current month.
     */
    public function month(): ?int
    {
        $month = $this->validated('month');

        if ($month === null && $this->reportView() === ReportService::VIEW_DAILY) {
            return now()->month;
        }

        return $month !== null ? (int) $month : null;
    }
}
