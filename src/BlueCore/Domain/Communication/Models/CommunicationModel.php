<?php

namespace BlueFission\BlueCore\Domain\Communication\Models;

use BlueFission\Obj;
use BlueFission\BlueCore\Model\ModelSql as Model;

class CommunicationModel extends Model
{
    private $_attachments = [];
    private $_parameters = [];

    protected $_table = ['communications'];
    protected $_fields = [
        'communication_id',
        'user_id',
        'recipient_id',
        'communication_type_id',
        'channel',
        'communication_content',
        'communication_status_id',
    ];

    protected $_related = [
        'users',
        'communication_parameters',
        'communication_attachments',
        'communication_types',
        // 'communication_channels',
        'communication_statuses',
    ];

    public function user()
    {
        return $this->ancestor('BlueFission\BlueCore\User\Models\UserModel', 'user_id');
    }

    public function recipient()
    {
        return $this->ancestor('BlueFission\BlueCore\User\Models\UserModel', 'recipient_id', 'user_id');
    }

    public function parameters()
    {
        return $this->descendents('BlueFission\BlueCore\Communication\Models\CommunicationParameterModel', 'communication_id');
    }

    public function attachments()
    {
        return $this->descendents('BlueFission\BlueCore\Communication\Models\CommunicationAttachmentModel', 'communication_id');
    }

    public function type()
    {
        return $this->ancestor('BlueFission\BlueCore\Communication\Models\CommunicationTypeModel', 'communication_type_id');
    }

    // public function channel()
    // {
    //     return $this->ancestor('BlueFission\BlueCore\Communication\Models\CommunicationChannelModel', 'communication_channel_id', );
    // }

    public function communication_status()
    {
        return $this->ancestor('BlueFission\BlueCore\Communication\Models\CommunicationStatusModel', 'communication_status_id', );
    }

    public function addAttachments($attachments = [])
    {
        $this->attachments = array_merge($this->_attachments, $attachments);
    }

    public function addParameters($parameters = [])
    {
        $this->parameters = array_merge($this->_parameters, $parameters);
    }

    public function write($values = null) : Obj
    {
        parent::write($values);
        $model = new CommunicationAttachmentModel();
        foreach($this->_attachments as $attachment) {
            $model->write([
                'communication_id' => $this->communication_id,
                'name' => $attachment['name'],
                'file_type' => $attachment['file_type'],
            ]);
        }
        $model = new CommunicationParameterModel();
        foreach($this->_parameters as $parameter) {
            $model->write([
                'communication_id' => $this->communication_id,
                'name' => $parameter['name'],
                'value' => $parameter['value'],
            ]);
        }

        return $this;
    }
}
