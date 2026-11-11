<?php

namespace App\Models;

use App\Enums\AttachmentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property AttachmentKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $uploaded_by_id
 */
#[Fillable(['attachable_type', 'attachable_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by_id'])]
class Attachment extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => AttachmentKind::class,
            'size' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
