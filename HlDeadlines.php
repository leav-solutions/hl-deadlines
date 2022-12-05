<?php
class Kbx_Plugins_HlDeadlines_HlDeadlines extends Kbx_Plugins_PluginBase {
    protected static $_runAsUser = 1;

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
        
    }
    
    public function runCli(array $params) {
        
    }
}