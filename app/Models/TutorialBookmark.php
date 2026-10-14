<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// User/content pivot: repository slugs are versioned content keys, not DB domain IDs.
#[Fillable(['user_id', 'tutorial_slug'])]
class TutorialBookmark extends Model {}
