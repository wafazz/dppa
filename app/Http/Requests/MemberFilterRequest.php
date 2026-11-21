<?php

namespace App\Http\Requests;

use App\Enums\MemberStatus;
use App\Models\BusinessCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberFilterRequest extends FormRequest
{
    /**
     * Columns that the member list may be sorted by.
     */
    private const SORTABLE = [
        'member_no',
        'applicant_name',
        'business_name',
        'status',
        'applied_at',
        'approved_at',
    ];

    /**
     * Route middleware restricts this request to authenticated users.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(MemberStatus::class)],
            'category_id' => ['nullable', 'integer', Rule::exists((new BusinessCategory)->getTable(), 'id')],
            'services' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
        ];
    }

    /**
     * @return array{q: ?string, status: ?string, category_id: ?int, services: ?string}
     */
    public function filters(): array
    {
        return [
            'q' => $this->filled('q') ? trim((string) $this->validated('q')) : null,
            'status' => $this->validated('status'),
            'category_id' => $this->filled('category_id') ? (int) $this->validated('category_id') : null,
            'services' => $this->filled('services') ? trim((string) $this->validated('services')) : null,
        ];
    }

    public function sortColumn(): string
    {
        return (string) ($this->validated('sort') ?? 'applied_at');
    }

    public function sortDirection(): string
    {
        return (string) ($this->validated('direction') ?? 'desc');
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 15);
    }
}
