<?php

namespace Visualbuilder\ExportScheduler\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Who, besides its owner, may look at a custom report.
 *
 * Visibility grants view and download only. Editing, deleting, running and
 * schedule management always remain with the owner, whatever the mode.
 */
enum ReportVisibility: string implements HasDescription, HasLabel
{
    /** Nobody but the owner. */
    case OWNER = 'owner';

    /** Every user of the single class named in visible_to_type. */
    case USER_TYPE = 'user_type';

    /** Only the users whose ids are listed in visible_to_ids, all of visible_to_type. */
    case NAMED_USERS = 'named_users';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OWNER => __('export-scheduler::scheduler.visibility_owner'),
            self::USER_TYPE => __('export-scheduler::scheduler.visibility_user_type'),
            self::NAMED_USERS => __('export-scheduler::scheduler.visibility_named_users'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::OWNER => __('export-scheduler::scheduler.visibility_owner_description'),
            self::USER_TYPE => __('export-scheduler::scheduler.visibility_user_type_description'),
            self::NAMED_USERS => __('export-scheduler::scheduler.visibility_named_users_description'),
        };
    }

    /**
     * Does this mode need a user class chosen?
     */
    public function needsType(): bool
    {
        return $this !== self::OWNER;
    }

    /**
     * Does this mode need an explicit list of users?
     */
    public function needsIds(): bool
    {
        return $this === self::NAMED_USERS;
    }
}
