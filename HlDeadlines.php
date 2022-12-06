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
            $this->view->data = [
                'projects' => $projects,
                'configs' => $configs,
                'projectsWithValues' => $projectsWithValues
            ];
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
                    $projectData['values'][(int)$config['dateAttribute']] = $this->_getValue((int)$projectId, (int)$config['dateAttribute']);
                    $projectData['values'][(int)$config['doneAttribute']] = (int)$this->_getValue((int)$projectId, (int)$config['doneAttribute']);
                    $projectData['values'][(int)$config['dateAttribute'].'_timestamp'] = $this->_dateStringToTimestamp($projectData['values'][(int)$config['dateAttribute']]);

                }
                return $projectData;
            },
            $projects
        );
    }
    private function _getValue(int $idProject, int $idAttribute):string {
        if ($idProject === 0 || $idAttribute === 0) {
            return '';
        }
        $values = Kbx_Attributes::getValue($idProject, Kbx_Libraries::$projectsLibraryId, $idProject, $idAttribute);
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
                    // check date value against current date - delay
                }

                return $config;
            },
            $configs
        );
        return [];
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
}