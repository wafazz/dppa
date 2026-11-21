<?php

namespace App\Models\Concerns;

use App\Models\AccountPosition;
use App\Models\AppSetting;
use App\Models\BusinessCategory;
use App\Models\ChamberPosition;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\PositionAppointment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every create and update of the model, with the changed fields and their old and new values.
 * Models list fields to leave out (passwords, secrets, tokens) through activityExcluded().
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model): void {
            /** @var self $model */
            $model->writeActivity('created', $model->activityValues($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            /** @var self $model */
            $changes = $model->getChanges();

            foreach ($model->activityExcluded() as $field) {
                unset($changes[$field]);
            }

            if ($changes === []) {
                return;
            }

            $values = [];

            foreach ($changes as $field => $new) {
                $values[$field] = [
                    'old' => $model->activityValues([$field => $model->getOriginal($field)])[$field] ?? null,
                    'new' => $model->activityValues([$field => $new])[$field] ?? null,
                ];
            }

            $model->writeActivity('updated', $values);
        });

        static::deleted(function (Model $model): void {
            /** @var self $model */
            $model->writeActivity('deleted', []);
        });
    }

    /**
     * Fields that never reach the audit trail.
     *
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return ['created_at', 'updated_at'];
    }

    /**
     * Keeps long text short and removes excluded fields from a set of values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function activityValues(array $values): array
    {
        foreach ($this->activityExcluded() as $field) {
            unset($values[$field]);
        }

        return array_map(
            fn (mixed $value): mixed => is_string($value) && mb_strlen($value) > 500
                ? mb_substr($value, 0, 500).'…'
                : $value,
            $values,
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function writeActivity(string $action, array $properties): void
    {
        $subject = $this->activitySubject();

        app(ActivityLogger::class)->record(
            $subject.'.'.$action,
            $this->activityDescription($action, $properties),
            null,
            $this,
            $properties === [] ? [] : ['changes' => $properties],
        );
    }

    /**
     * The name the trail uses for this model, such as "member" or "chamber_position".
     */
    protected function activitySubject(): string
    {
        return match (static::class) {
            User::class => 'account',
            Member::class => 'member',
            ChamberPosition::class => 'position',
            PositionAppointment::class => 'appointment',
            BusinessCategory::class => 'category',
            Invitation::class => 'invitation',
            AppSetting::class => 'setting',
            AccountPosition::class => 'management_position',
            default => class_basename($this),
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function activityDescription(string $action, array $properties): string
    {
        $fields = array_keys($properties);

        return $fields === []
            ? "#{$this->getKey()}"
            : "#{$this->getKey()}: ".implode(', ', $fields);
    }
}
