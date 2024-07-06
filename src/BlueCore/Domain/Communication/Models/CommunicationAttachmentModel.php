<?php

namespace BlueFission\BlueCore\Domain\Communication\Models;

use BlueFission\BlueCore\Model\ModelSql as Model;

class CommunicationAttachmentModel extends Model
{
    protected $_table = ['communication_attachments'];
    protected $_fields = [
        'communication_attachment_id',
        'communication_id',
        'file_path',
        'file_type',
    ];
}
