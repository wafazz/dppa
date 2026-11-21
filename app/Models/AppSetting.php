<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored integration setting. Values flagged as secret are encrypted by IntegrationSettings.
 */
#[Fillable(['key', 'value', 'is_secret', 'updated_by_user_id'])]
class AppSetting extends Model
{
    use LogsActivity {
        activityValues as protected traitActivityValues;
    }

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return array_merge(['created_at', 'updated_at'], []);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    /**
     * Secret settings never reach the audit trail, not even as old and new values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function activityValues(array $values): array
    {
        if (array_key_exists('value', $values) && $this->is_secret) {
            $values['value'] = '[redacted]';
        }

        return $this->traitActivityValues($values);
    }
}
