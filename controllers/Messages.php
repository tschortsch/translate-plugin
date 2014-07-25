<?php namespace Rainlab\Translate\Controllers;

use Flash;
use Request;
use BackendMenu;
use Backend\Widgets\Grid;
use Backend\Classes\Controller;
use Rainlab\Translate\Models\Message;
use Rainlab\Translate\Models\Locale;
use System\Console\CacheClear;

/**
 * Messages Back-end Controller
 */
class Messages extends Controller
{
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('October.System', 'system', 'settings');

        $this->addJs('/plugins/rainlab/translate/assets/js/messages.js');
        $this->addCss('/plugins/rainlab/translate/assets/css/messages.css');
    }

    public function index()
    {
        $this->bodyClass = 'slim-container';
        $this->pageTitle = 'Translate Messages';
        $this->prepareGrid();
    }

    public function onRefresh()
    {
        $this->prepareGrid();
        return ['#messagesContainer' => $this->makePartial('messages')];
    }

    public function onClearCache()
    {
        CacheClear::fireInternal();
        Flash::success('Cleared the application cache successfully!');
    }

    public function onScanMessages()
    {
        Flash::info('Coming soon!');
    }

    public function prepareGrid()
    {
        $fromCode = post('locale_from', null);
        $toCode = post('locale_to', Locale::getDefault()->code);

        /*
         * Page vars
         */
        $this->vars['hideTranslated'] = post('hide_translated', false);
        $this->vars['defaultLocale'] = Locale::getDefault();
        $this->vars['locales'] = Locale::all();
        $this->vars['selectedFrom'] = $selectedFrom = Locale::findByCode($fromCode);
        $this->vars['selectedTo'] = $selectedTo = Locale::findByCode($toCode);

        /*
         * Make grid config, make default column read only
         */
        $config = $this->makeConfig('config_grid.yaml');
        $config->data = $this->getGridData($selectedFrom, $selectedTo);
        if (!$selectedFrom) $config->columns['from']['readOnly'] = true;
        if (!$selectedTo) $config->columns['to']['readOnly'] = true;

        /*
         * Make grid widget
         */
        $widget = new Grid($this, $config);
        $widget->bindEvent('grid.dataChanged', [$this, 'updateGridData']);
        $widget->bindToController();
        $this->vars['grid'] = $widget;
    }

    protected function getGridData($from, $to)
    {
        $messages = Message::all();

        $fromCode = $from ? $from->code : null;
        $toCode = $to ? $to->code : null;

        $data = [];
        foreach ($messages as $message) {
            $data[] = [
                'code' => $message->code,
                'from' => $message->forLocale($fromCode),
                'to' => $message->forLocale($toCode)
            ];
        }

        return $data;
    }

    public function updateGridData($changes)
    {
        if (!is_array($changes))
            return;

        foreach ($changes as $change) {
            if (!$code = array_get($change, 'rowData.code'))
                continue;

            if (!$columnType = array_get($change, 'keyName'))
                continue;

            if ($columnType != 'to' && $columnType != 'from')
                continue;

            if (!$locale = post('locale_'.$columnType))
                continue;

            if (!$item = Message::whereCode($code)->first())
                continue;

            $newValue = array_get($change, 'newValue');
            $item->toLocale($locale, $newValue);
        }
    }

}