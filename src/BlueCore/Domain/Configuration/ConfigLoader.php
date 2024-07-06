<?php

namespace BlueFission\BlueCore\Domain\Configuration;

use BlueFission\BlueCore\Models\ConfigurationModel;

class ConfigLoader
{
    public static function load(int $configId)
    {
        $configModel = new ConfigurationModel();
        $configModel->clear();
        $configModel->assign(['config_id' => $configId]);
        $configModel->read();

        if (!$configModel->exists()) {
            throw new InvalidArgumentException("Configuration with ID {$configId} not found.");
        }

        return json_decode($configModel->configuration_data);
    }
}
