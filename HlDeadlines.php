<?php
class Kbx_Plugins_HlDeadlines_HlDeadlines extends Kbx_Plugins_PluginBase {
    protected static $_runAsUser = 1;

    /**
     * @var int
     */
    protected static $_configurationLibraryId = 110;
    /**
     * @var int
     */
    protected static $_configurationDateAttributeId = 869;
    /**
     * @var int
     */
    protected static $_configurationDoneAttributeId = 868;
    /**
     * @var int
     */
    protected static $_configurationDelayAttributeId = 873;
    /**
     * @var int
     */
    protected static $_configurationTitleAttributeId = 871;
    /**
     * @var int
     */
    protected static $_configurationBodyAttributeId = 872;
    /**
     * @var int
     */
    protected static $_configurationRecipientsAttributeId = 870;
    /**
     * @var array
     */
    protected static $_projectsWorkflowIds = [63];//[43];

    /**
     * @var string|bool
     */
    protected $layout;
    // phpcs:ignore Zend.NamingConventions.ValidVariableName
    /**
     * @var Zend_View
     */
    protected $view;
    // phpcs:ignore Zend.NamingConventions.ValidVariableName
    /**
     * @var string|bool
     */
    protected $viewRenderer;
    // phpcs:ignore Zend.NamingConventions.ValidVariableName
    /**
     * @var array
     */
    protected $params;
    /**
     * @var array
     */
    protected $_dateFormats;
    /**
     * @var string
     */
    protected $_lang;
    // phpcs:ignore Zend.NamingConventions.ValidVariableName
    /**
     * @var string
     */
    public function __construct($view, array $params) {
        $this->init();

        $this->view         = $view;
        $this->params       = $params;
        $this->layout       = '';
        if (!isset($params['execute'])) {
            $params['execute'] = 'index';
        }
        $this->viewRenderer = $params['execute'];

        $this->_dateFormats = Zend_Registry::getInstance()->dateFormats;
        $this->_lang = Zend_Registry::getInstance()->Zend_Locale->getLanguage();

    }
    public function getViewsPath(): string {
        return __DIR__ . '/views/';
    }
    /**
    * @return string|bool
    */
    public function getViewRenderer() {
        return $this->viewRenderer;
    }
    /**
    * @return string|bool
    */
    public function getLayout() {
        return $this->layout;
    }
    public function getInitButton(): array {
        return [
            'icon'   => 'icon_manage',
            'action' => "openDialog('/plugin/index/plugin/HlDeadlines/execute/index',600,400);",
            'label'  => 'HL Deadlines'
        ];
    }

    public static function getInitButtonStatic(): array {
        return [
            'icon'   => 'icon_manage',
            'action' => "openDialog('/plugin/index/plugin/HlDeadlines/execute/index',600,400);",
            'label'  => 'HL Deadlines'
        ];
    }
    public function action(): void {
        $this->layout           = 'ajax';
        $this->view->title      = "HL Deadlines";
        $this->view->dialogId   = isset($this->params['dialogId']) ? $this->params['dialogId'] : '';
        $functionName           = $this->params['execute'];
        try {
            $this->$functionName();
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            $this->logError($e->getTraceAsString());
            $this->logEnd(1);
        }
    }
    public function index() {
        try {
            $projects = $this->_getProjectsByStatus();
            $configs = $this->_getConfigurations();
            $projectsWithValues = $this->_retrieveProjectsValues($projects, $configs);
            $configsWithProjects = $this->_groupProjectsByDeadline($projectsWithValues, $configs);
            $configsWithProjects = $this->_filterDeadlinesWithoutProject($configsWithProjects);
            $configsWithProjectsAndNotifications = $this->_generateNotificationsTexts($configsWithProjects);
            $configsWithProjectsAndNotificationsAndRecipients = $this->_addRecipients($configsWithProjectsAndNotifications);
            $this->view->data = [
                'projects' => $projects,
                'configs' => $configs,
                'projectsWithValues' => $projectsWithValues,
                'configWithProjects' => $configsWithProjects,
                'configsWithProjectsAndNotifications' => $configsWithProjectsAndNotifications,
                'configsWithProjectsAndNotificationsAndRecipients' => $configsWithProjectsAndNotificationsAndRecipients
            ];
            $this->_sendNotifications($configsWithProjectsAndNotificationsAndRecipients);
        } catch (Exception $e) {
            $this->view->data = [
                'message' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ];
        }
    }
    
    public function runCli(array $params) {
        
    }

    private function _getProjectsByStatus(): array {
        $db = Zend_Registry::getInstance()->dbAdapter;
        $select = $db->select()
            ->from('k_record_4', ['id_record'])
            ->where('lca_id IN (?)', implode(',', self::$_projectsWorkflowIds));
        $res = $db->fetchAll($select);
        return array_column($res, 'id_record');
    }
    private function _getConfigurations(): array {
        $db = Zend_Registry::getInstance()->dbAdapter;
        $select = $db->select()
            ->from(
                'k_record_'.self::$_configurationLibraryId, 
                [
                    'id_record',
                    'dateAttribute' => 'attribute_'.self::$_configurationDateAttributeId,
                    'doneAttribute' => 'attribute_'.self::$_configurationDoneAttributeId,
                    'delay' => 'attribute_'.self::$_configurationDelayAttributeId
                ]
            )
            ->where('attribute_'.self::$_configurationDateAttributeId.' IS NOT NULL')
            ->where('lca_id IS NULL');
        $res = $db->fetchAll($select);
        $configs = array_map(
            function($config) {
                $configDateLimit = new DateTime();
                $delayStr = '-'.(abs((int)$config['delay']));
                $configDateLimit->modify("$delayStr day");
                $configDateLimit->setTime(0, 0, 0);
                $config['limitTimestamp'] = $configDateLimit->getTimestamp();
                return $config;
            },
            $res
        );
        return $configs;
    }
    private function _retrieveProjectsValues(array $projects, array $configs): array {
        return array_map(
            function ($projectId) use ($configs) {
                $projectData = [
                    'id_record' => $projectId,
                    'values' => []
                ];
                foreach ($configs as $config) {
                    $projectData['values'][(int)$config['dateAttribute']] = $this->_getValue(
                        (int)$projectId, 
                        Kbx_Libraries::$projectsLibraryId, 
                        (int)$config['dateAttribute'],
                        (int)$projectId
                    );
                    $projectData['values'][(int)$config['doneAttribute']] = (int)$this->_getValue(
                        (int)$projectId, 
                        Kbx_Libraries::$projectsLibraryId, 
                        (int)$config['doneAttribute'],
                        (int)$projectId
                    );
                    $projectData['values'][(int)$config['dateAttribute'].'_timestamp'] = $this->_dateStringToTimestamp($projectData['values'][(int)$config['dateAttribute']]);
                }
                return $projectData;
            },
            $projects
        );
    }
    private function _getValue(int $idRecord, int $idLibrary, int $idAttribute, int $idProject = 0):string {
        if ($idRecord === 0 || $idLibrary === 0 || $idAttribute === 0) {
            return '';
        }
        $values = Kbx_Attributes::getValue($idProject, $idLibrary, $idRecord, $idAttribute);
        return sizeof($values)>0
            ? $values[0]['value']
            : '';
    }
    private function _groupProjectsByDeadline(array $projectsWithValues, array $configs): array {
        $configsWithProjects = array_map(
            function ($config) use ($projectsWithValues) {
                $config['matchingProjects'] = [];
                foreach ($projectsWithValues as $project) {
                    // check the done value
                    if ($project['values'][(int)$config['doneAttribute']] == 1) {
                        // ignore this project cause marked as done for this config
                        continue;
                    }
                    if ($project['values'][(int)$config['dateAttribute'].'_timestamp'] <= $config['limitTimestamp']) {
                        $config['matchingProjects'][] = [
                            'id_record' => $project['id_record'],
                            'timestamp' => $project['values'][(int)$config['dateAttribute'].'_timestamp']
                        ];
                    }
                }

                return $config;
            },
            $configs
        );
        return $configsWithProjects;
    }
    private function _filterDeadlinesWithoutProject(array $configs): array {
        return array_filter(
            $configs,
            function ($config) {
                return sizeof($config['matchingProjects']) > 0;
            }
        );
    }
    private function _dateStringToTimestamp(string $dateStr): int {
        if ((string)intval($dateStr) === $dateStr) {
            // we recieved a timestamp, no need to convert, but set to 00h:00m:00s
            $parsed = new DateTime();
            $parsed->setTimestamp($dateStr);

        } else {
            
            $parsed = Kbx_Dates::date_create_from_format($this->_dateFormats[$this->_lang]['php'], $dateStr);
        }
        $parsed->setTime(0,0,0);
        return $parsed->getTimestamp();
    }
    private function _generateNotificationsTexts(array $configsWithProjects): array {
        return array_map(
            function($config) {
                $config['title'] = $this->_getValue(
                    $config['id_record'], 
                    self::$_configurationLibraryId, 
                    self::$_configurationTitleAttributeId
                );
                $config['body'] = $this->_getValue(
                    $config['id_record'], 
                    self::$_configurationLibraryId, 
                    self::$_configurationBodyAttributeId
                );
                $deadlineDate = (string)date($this->_dateFormats[$this->_lang]['php'], $config['limitTimestamp']);
                $config['matchingProjects'] = array_map(
                    function($project) use (&$config, $deadlineDate) {
                        $projectRecord = new Kbx_Records(
                            $project['id_record'], 
                            Kbx_Libraries::$projectsLibraryId, 
                            $project['id_record']
                        );
                        $projectLabel = $projectRecord->getLabel();
                        $projectDate = (string)date($this->_dateFormats[$this->_lang]['php'], $project['timestamp']);
                        $project['title'] = $this->_replacePlaceholders($config['title'], $projectLabel, $projectDate, $deadlineDate);
                        $project['body'] = $this->_replacePlaceholders($config['body'], $projectLabel, $projectDate, $deadlineDate);
                        return $project;
                    },
                    $config['matchingProjects']
                );
                return $config;
            },
            $configsWithProjects
        );
    }
    private function _addRecipients(array $configs): array {
        return array_map(
            function($config) {
                $recipientsValues = Kbx_Attributes::getValue(
                    0, 
                    self::$_configurationLibraryId, 
                    $config['id_record'], 
                    self::$_configurationRecipientsAttributeId
                );
                $config['recipients'] = [];
                foreach ($recipientsValues as $recipientValue) {
                    $mail = Kbx_Users::getLogin($recipientValue['value']);
                    $config['recipients'][] = [
                        'id' => $recipientValue['value'],
                        'mail' => $mail
                    ];
                }
                return $config;
            },
            $configs
        );
    }
    private function _sendNotifications(array $configs): void {
        $whoIsOnline = Kbx_RealTimeMessage::getConnectedUsers();
        foreach ($configs as $config) {
            foreach ($config['recipients'] as $recipient) {
                foreach ($config['matchingProjects'] as $project) {
                    $this->_sendNotification($whoIsOnline, $recipient, $project['title'], $project['body']);
                }
            }
            

        }
    }
    private function _sendNotification(array $whoIsOnline, array $user, string $title, string $body) {
        if (in_array((int)$user['id'], $whoIsOnline)) {
            $res = Kbx_RealTimeMessage::emit(
                [
                    'type' => 'notification',
                    'important' => true,
                    'event' => [
                        'title' => $title,
                        'element' => str_replace("\r", "\n", $body)
                    ]
                ], 
                $user['id']
            );
            error_log("notif sent to user ".print_r($user, true).", return ".(int)$res);
        } else {
            error_log("user ".print_r($user, true)." is not in onlineusers : ".print_r($whoIsOnline, true));
        }

    }
    private function _replacePlaceholders(string $text, string $projectLabel, string $projectDate, string $deadlineDate): string {
        $text = str_replace('{project_name}', $projectLabel, $text);
        $text = str_replace('{project_date}', $projectDate, $text);
        $text = str_replace('{deadline_date}', $deadlineDate, $text);
        return $text;
    }
}