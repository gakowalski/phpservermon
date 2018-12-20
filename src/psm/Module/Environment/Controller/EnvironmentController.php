<?php
/**
 * PHP Server Monitor
 * Monitor your servers and websites.
 *
 * This file is part of PHP Server Monitor.
 * PHP Server Monitor is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PHP Server Monitor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PHP Server Monitor.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     phpservermon
 * @author      Kuliievych Oleksandr <borove@gmail.com>
 * @copyright   Copyright (c) 2018 Kuliievych Oleksandr <borove@gmail.com>
 * @license     http://www.gnu.org/licenses/gpl.txt GNU GPL v3
 * @version     Release: @package_version@
 * @link        http://www.phpservermonitor.org/
 **/

namespace psm\Module\Environment\Controller;
use psm\Module\AbstractController;
use psm\Service\Database;

class EnvironmentController extends AbstractController {

    /**
     * Checkboxes
     * @var array $checkboxes
     */
    protected $checkboxes = array(

    );

    /**
     * Fields for saving
     * @var array $fields
     */
    protected $fields = array(

    );

    private $environment_id;

    function __construct(Database $db, \Twig_Environment $twig) {
        parent::__construct($db, $twig);

        $this->setMinUserLevelRequired(PSM_USER_ADMIN);
        $this->setCSRFKey('environment');

        $this->setActions(array(
            'index','edit', 'save', 'delete'
        ), 'index');

        // make sure only admins are allowed to edit/delete environments:
        $this->setMinUserLevelRequiredForAction(PSM_USER_ADMIN, array(
            'delete', 'edit', 'save'
        ));

        $this->twig->addGlobal('subtitle', psm_get_lang('menu', 'environment'));
        $this->environment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    }


    protected function executeIndex() {
        $tpl_data = $this->getLabels();

        $config_db = $this->db->select(
            PSM_DB_PREFIX.'environments',
            null,
            array('environment_id', 'name')
        );

        $config = array();
        foreach ($config_db as $entry) {
            $config[$entry['key']] = $entry['value'];
        }

        // generate language array
        $lang_keys = psm_get_langs();
        $tpl_data['language_current'] = (isset($config['language']))
            ? $config['language']
            : 'en_US';
        $tpl_data['languages'] = array();
        foreach ($lang_keys as $key => $label) {
            $tpl_data['languages'][] = array(
                'value' => $key,
                'label' => $label,
            );
        }

        $sidebar = new \psm\Util\Module\Sidebar($this->twig);
        $this->setSidebar($sidebar);

        // check if user is admin, in that case we add the buttons
        if ($this->getUser()->getUserLevel() == PSM_USER_ADMIN) {
            $modal = new \psm\Util\Module\Modal($this->twig, 'delete', \psm\Util\Module\Modal::MODAL_TYPE_DANGER);
            $this->addModal($modal);
            $modal->setTitle(psm_get_lang('environment', 'delete_title'));
            $modal->setMessage(psm_get_lang('environment', 'delete_message'));
            $modal->setOKButtonLabel(psm_get_lang('system', 'delete'));

            $sidebar->addButton(
                'add_new',
                psm_get_lang('system', 'add_new'),
                psm_build_url(array('mod' => 'environment', 'action' => 'edit')),
                'plus icon-white', 'success'
            );
        }

        $environments = $this->getEnvironments();

        for ($x = 0; $x < count($environments); $x++) {
            $environments[$x] = $this->formatEnvironment($environments[$x]);
        }

        $tpl_data['user_level'] = $this->getUser()->getUserLevel();
        $tpl_data['environments'] = $environments;

        return $this->twig->render('module/environment/environment/list.tpl.html', $tpl_data);
    }

    protected function executeEdit() {
        $back_to = isset($_GET['back_to']) ? $_GET['back_to'] : '';

        $tpl_data = $this->getLabels();
        $tpl_data['edit_environment_id'] = $this->environment_id;
        $tpl_data['url_save'] = psm_build_url(array(
            'mod' => 'environment',
            'action' => 'save',
            'id' => $this->environment_id,
            'back_to' => $back_to,
        ));


        $tpl_data['url_go_back'] = psm_build_url(array('mod' => 'environment'));


        $tpl_data['users'] = $this->db->select(PSM_DB_PREFIX.'users', null, array('user_id', 'name'), '', 'name');

        switch ($this->environment_id) {
            case 0:
                // insert mode
                $tpl_data['titlemode'] = psm_get_lang('system', 'insert');

                $edit_server = $_POST;
                break;
            default:
                // edit mode
                // get environment entry
                $edit_environment = $this->getEnvironmentById($this->environment_id);
                if (empty($edit_environment)) {
                    $this->addMessage(psm_get_lang('environment', 'error_environment_no_match'), 'error');
                    return $this->runAction('index');
                }
                $tpl_data['titlemode'] = psm_get_lang('system', 'edit').' '.$edit_environment['name'];

                break;
        }

        if (!empty($edit_environment)) {
            // attempt to prefill previously posted fields
            foreach ($edit_environment as $key => $value) {
                $edit_environment[$key] = psm_POST($key, $value);
            }

            $tpl_data = array_merge($tpl_data, array(
                'edit_value_name' => $edit_environment['name'],
                'edit_environment_id' => $edit_environment['environment_id'],
                'label_save' => psm_get_lang('system', 'save'),
                'label_go_back' => psm_get_lang('system', 'go_back'),
            ));
        }


        return $this->twig->render('module/environment/environment/edit.tpl.html', $tpl_data);
    }

    /**
     * Executes the saving of one of the environments
     */
    protected function executeSave() {
        if (empty($_POST)) {
            // dont process anything if no data has been posted
            return $this->executeIndex();
        }
        $this->environment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        $clean = array(
            'name' => trim(strip_tags(psm_POST('name', ''))),
        );

        $environment_validator = new \psm\Util\Environment\EnvironmentValidator($this->db);

        if($this->environment_id > 0)
            $environment_from_db = $this->db->selectRow(PSM_DB_PREFIX.'environments', array('environment_id' => $this->environment_id), array('environment_id', 'name'));

        try {
            if ($this->environment_id > 0) {
                $environment_validator->environmentId($this->environment_id);
            }

            if(empty($this->environment_id) || (!empty($this->environment_id) && !empty($environment_from_db)
                    && strcmp(trim($environment_from_db['name']), trim($clean['name'])) !== 0  ))
                $environment_validator->name($clean['name']);

        } catch (\InvalidArgumentException $ex) {
            $this->addMessage(psm_get_lang('environment', 'error_'.$ex->getMessage()), 'error');
            return $this->executeEdit();
        }

        // check for edit or add
        if ($this->environment_id > 0) {
            // edit
            $this->db->save(
                PSM_DB_PREFIX.'environments',
                $clean,
                array('environment_id' => $this->environment_id)
            );
            $this->addMessage(psm_get_lang('environment', 'updated'), 'success');
        } else {
            // add
            $this->environment_id = $this->db->save(PSM_DB_PREFIX.'environments', $clean);
            $this->addMessage(psm_get_lang('environment', 'inserted'), 'success');
        }

        $back_to = isset($_GET['back_to']) ? $_GET['back_to'] : 'index';
        if ($back_to == 'view') {
            return $this->runAction('view');
        } else {
            return $this->runAction('index');
        }
    }


    /**
     * Executes the deletion of one of the environments
     */
    protected function executeDelete() {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            // do delete

            $hasServer = $this->db->selectRow(PSM_DB_PREFIX.'servers', array('environment_id' => $id));
            if(!empty($hasServer)) {
                $this->addMessage(psm_get_lang('environment', 'delete_error'), 'error');
                return $this->runAction('index');
            }

            $this->db->delete(PSM_DB_PREFIX.'environments', array('environment_id' => $id));

            $this->addMessage(psm_get_lang('environment', 'deleted'), 'success');
        }
        return $this->runAction('index');
    }

    protected function getEnvironments() {
        $sql = "SELECT `environment_id`, `name` FROM `".PSM_DB_PREFIX."environments` ORDER BY `name`;";
        return $this->db->query($sql);
    }

    protected function getEnvironmentById($id) {
        $environment = array();
        $sql = "SELECT `environment_id`, `name` FROM `".PSM_DB_PREFIX."environments` WHERE environment_id = {$id} LIMIT 1;";
        $result = $this->db->query($sql);
        if($result && !empty($result) && count($result) > 0)
            $environment = $result[0];
        return $environment;
    }

    protected function getLabels() {
        return array(
            'label_label' => psm_get_lang('environment', 'label'),
            'label_delete' => psm_get_lang('system', 'delete'),
            'label_edit' => psm_get_lang('system', 'edit'),
            'label_action' => psm_get_lang('system', 'action'),
            'label_name' => psm_get_lang('system', 'name'),
            'label_save' => psm_get_lang('system', 'save'),
            'label_go_back' => psm_get_lang('system', 'go_back'),
        );
    }

    protected function formatEnvironment($env) {
        $url_actions = array('delete', 'edit');
        foreach ($url_actions as $action) {
            $env['url_'.$action] = psm_build_url(array(
                'mod' => 'environment',
                'action' => $action,
                'id' => $env['environment_id'],
            ));
        }

        return $env;
    }
}
