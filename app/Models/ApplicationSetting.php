<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Jobs\PlanGuard;
use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Settings\Enums\SettingValueType;
use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\ApplicationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'scope', 'key', 'value_type', 'value_text', 'value_integer', 'value_boolean', 'credential_id'])]
class ApplicationSetting extends Model
{
    /** @use HasFactory<ApplicationSettingFactory> */
    use BelongsToWorkspace, HasFactory, HasPublicUuid;

    protected static function booted(): void
    {
        static::saving(function (ApplicationSetting $setting): void {
            if (is_string($setting->value_text)) {
                app(PlanGuard::class)->assertSafe(['value' => $setting->value_text]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => SettingScope::class,
            'value_type' => SettingValueType::class,
            'value_boolean' => 'boolean',
            'value_integer' => 'integer',
        ];
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }
}
