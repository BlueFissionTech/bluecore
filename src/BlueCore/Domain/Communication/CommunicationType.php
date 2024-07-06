<?php
namespace BlueFission\BlueCore\Domain\Communication;

use BlueFission\BlueCore\ValueObject;

class CommunicationType extends ValueObject
{
    // User to System
    const USER_REQUEST = 'user-request';
    const USER_FEEDBACK = 'user-feedback';
    const USER_ERROR_REPORT = 'user-error-report';

    // System to User
    const SYSTEM_RESPONSE = 'system-response';
    const SYSTEM_PROMPT = 'system-prompt';
    const SYSTEM_ERROR = 'system-error';
    const SYSTEM_SUCCESS = 'system-success';

    // User to User
    const USER_DIRECT_MESSAGE = 'user-direct-message';
    const USER_GROUP_MESSAGE = 'user-group-message';
    const USER_MENTION = 'user-mention';

    // System to System
    const SYSTEM_INTERNAL_INFO = 'system-internal-info';
    const SYSTEM_INTERNAL_UPDATE = 'system-internal-update';
    const SYSTEM_INTERNAL_WARNING = 'system-internal-warning';
    const SYSTEM_INTERNAL_ERROR = 'system-internal-error';

    public $communication_type_id;
    public $name;
    public $label;
}

