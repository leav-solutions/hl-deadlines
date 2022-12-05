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
    protected static $_projectsWorkflowIds = [43];

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
            $this->view->data = [
                'projects' => $this->_getProjectsByStatus(),
                'configs' => $this->_getConfigurations()
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
            );
        $res = $db->fetchAll($select);
        return $res;
    }
}