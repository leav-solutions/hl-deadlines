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
    protected static $_configurationBodyAttributeId = 877;
    /**
     * @var int
     */
    protected static $_configurationRecipientsAttributeId = 870;
    /**
     * @var int
     */
    protected static $_configurationTestWorkflowId = 88;
    /**
     * @var array
     */
    protected static $_projectsWorkflowIds = [43];
    /**
     * @var int
     */
    protected static $_projectsWorkflowTestId = 63;
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
    /**
     * @var int
     */
    protected $_test;
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
        $this->_test = 0;

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
            /*$this->logError($e->getMessage());
            $this->logError($e->getTraceAsString());
            $this->logEnd(1);*/
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }
    public function index() {
        $this->view->testStatusId = self::$_configurationTestWorkflowId;
        $this->view->testStatusLabel = Kbx_Roles::getTranslationLabel(self::$_configurationTestWorkflowId);
        $this->view->projectsTestStatusId = self::$_projectsWorkflowTestId;
        $this->view->projectsTestStatusLabel = Kbx_Roles::getTranslationLabel(self::$_projectsWorkflowTestId);
    }
    public function overview() {
        $this->layout = 'layout';

        $this->_test = 1;

        $this->view->lastUpdate = date($this->_dateFormats[$this->_lang]['php'].' H:i:s', time());
        $projects = $this->_getProjectsByStatus();
        $configs = $this->_getConfigurations();
        $projectsWithValues = $this->_retrieveProjectsValues($projects, $configs);
        $configsWithProjects = $this->_groupProjectsByDeadline($projectsWithValues, $configs);
        $configsWithProjects = $this->_filterDeadlinesWithoutProject($configsWithProjects);
        $this->view->data = [
            'projects' => $projects,
            'configs' => $configs,
            'projectsWithValues' => $projectsWithValues,
            'configsWithProjects' => $configsWithProjects
        ];
    }
    private function _run() {
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
    public function runTest() {
        $this->viewRenderer = "run";
        $this->_test = (int)$this->params['test_mode'];
        $this->view->test_mode = $this->_test;
        $this->_run();
        $this->view->data['params'] = $this->params;
    }
    
    public function runCli(array $params) {
        
    }

    private function _getProjectsByStatus(): array {
        $db = Zend_Registry::getInstance()->dbAdapter;
        $select = $db->select()
            ->from('k_record_4', ['id_record']);
        if ($this->_test === 1) {
            $select->where('lca_id=?', self::$_projectsWorkflowTestId);
        } else {
            $select->where('lca_id IN (?)', implode(',', self::$_projectsWorkflowIds));
        }
            
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
            ->where('attribute_'.self::$_configurationDateAttributeId.' IS NOT NULL');
            
            if ($this->_test === 1) {
                $select->where('lca_id=?', self::$_configurationTestWorkflowId);
            } else {
                $select->where('lca_id IS NULL');
            }

        $configs = $db->fetchAll($select);
        
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
                    $delayStr = '-'.(abs((int)$config['delay']));
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
                    $triggerDate = new DateTime();
                    $triggerDate->setTimestamp($projectData['values'][(int)$config['dateAttribute'].'_timestamp']);
                    $triggerDate->modify("$delayStr day");
                    $triggerDate->setTime(0, 0, 0);
                    $projectData['values'][(int)$config['dateAttribute'].'_triggerDate'] = date($this->_dateFormats[$this->_lang]['php'], $triggerDate->getTimestamp());
                    $projectData['values'][(int)$config['dateAttribute'].'_triggerDate_timestamp'] = $triggerDate->getTimestamp();
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
        $today = new DateTime();
        $today->setTime(0, 0, 0);;
        $todayTimeStamp = $today->getTimestamp();
        $configsWithProjects = array_map(
            function ($config) use ($projectsWithValues, $todayTimeStamp) {
                $config['matchingProjects'] = [];
                foreach ($projectsWithValues as $project) {
                    // check the done value
                    if ($project['values'][(int)$config['doneAttribute']] == 1) {
                        // ignore this project cause marked as done for this config
                        continue;
                    }
                    //if ($project['values'][(int)$config['dateAttribute'].'_timestamp'] <= $config['limitTimestamp']) {
                    if ($todayTimeStamp >= $project['values'][(int)$config['dateAttribute'].'_triggerDate_timestamp']) {
                        $config['matchingProjects'][] = [
                            'id_record' => $project['id_record'],
                            'timestamp' => $project['values'][(int)$config['dateAttribute'].'_timestamp'],
                            'date' => $project['values'][(int)$config['dateAttribute']],
                            'triggerDate' => $project['values'][(int)$config['dateAttribute'].'_triggerDate']
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
                $configRecord = new Kbx_Records($config['id_record'], self::$_configurationLibraryId);
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
                
                $deadlineName = (string)$configRecord->getLabel();
                $config['matchingProjects'] = array_map(
                    function($project) use (&$config, $deadlineName) {
                        $deadlineDate = $project['triggerDate'];
                        $projectRecord = new Kbx_Records(
                            $project['id_record'], 
                            Kbx_Libraries::$projectsLibraryId, 
                            $project['id_record']
                        );
                        $projectLabel = $projectRecord->getLabel();
                        $projectDate = $project['date'];
                        $project['title'] = $this->_replacePlaceholders(
                            $config['title'], 
                            $projectLabel, 
                            $projectDate, 
                            $deadlineDate, 
                            $deadlineName
                        );
                        $project['body'] = $this->_replacePlaceholders(
                            $config['body'], 
                            $projectLabel, 
                            $projectDate, 
                            $deadlineDate, 
                            $deadlineName
                        );
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
                if ($this->_test !== 0) {
                    $recipientsValues = [
                        [
                            'value' => Kbx_Users::getCurrentUserId()
                        ]
                    ];
                }
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
        }
        //if ($this->_test !== 1) {
            $mailer = new Kbx_Mail();
            $mailer->send($user['mail'], '', $title, $body);
        //}
    }
    private function _replacePlaceholders(string $text, string $projectLabel, string $projectDate, string $deadlineDate, string $deadlineName): string {
        $text = str_replace('{project_name}', $projectLabel, $text);
        $text = str_replace('{project_date}', $projectDate, $text);
        $text = str_replace('{trigger_date}', $deadlineDate, $text);
        $text = str_replace('{deadline_name}', $deadlineName, $text);
        return $text;
    }
}