<?php
namespace Explorer\Controller;

include 'Explorer/Model/PHPItemTree.php';
include 'Explorer/Model/PHPItemTreeWithFunctions.php';
include 'Explorer/Model/ClassTree.php';
include 'Explorer/Model/FunctionTree.php';
include 'Explorer/Model/ExtensionTree.php';
include 'Explorer/GUI/MainWindow.php';
include 'Explorer/GUI/DocViewer.php';

class InitFuncFilterIterator extends \FilterIterator {
    public function current() {
        return parent::current()->getName();
    }
    public function accept() {
        return strpos($this->current(), 'init') === 0;
    }
}

class MainWindowController {
    protected $glade;
    protected $mainWindow;
    /**
     *
     * @var \Explorer\Manual\Manual
     */
    protected $manual;

    public function __construct($file) {
        $this->loadGlade($file);
	$this->mainWindow = new \Explorer\GUI\MainWindow($this);
        $status = $this->glade->get_widget('loadingprogress');

        $r = new \ReflectionObject($this);
        $methods = iterator_to_array(new InitFuncFilterIterator(new \ArrayIterator($r->getMethods())));
        $count = count($methods);
        $i = 0;
        foreach ($methods as $method) {
            $this->$method();

            $status->set_pulse_step(++$i/$count);
            while (\Gtk::events_pending()) {
                \Gtk::main_iteration();
            }
        }

        $this->glade->get_widget('loadingwindow')->set_visible(false);
        $this->mainWindow->show();
    }

    public function initFunctionList() {
	$store = new \Explorer\Model\FunctionTree();
        $this->mainWindow->fillFunctionTree($store);
    }

    public function initClassTree() {
        $store = new \Explorer\Model\ClassTree();
        $this->mainWindow->fillClassTree($store);
    }

    public function initExtensionTree() {
	$store = new \Explorer\Model\ExtensionTree();
        $this->mainWindow->fillExtensionTree($store);
    }

    public function getManual() {
	if (!$this->manual) {
	    $config = \Explorer\Config::getInstance();
	    $this->manual = new \Explorer\Manual\Manual($config['datadir'], $config['language']);
	}
	return $this->manual;
    }

    private function loadGlade($file) {
        $glade = file_get_contents($file);
        $glade = \GladeXML::new_from_buffer($glade);
         //$glade = new \GladeXML($file);

        $glade->signal_autoconnect_instance($this);
        $this->glade = $glade;
    }

    public function getGlade() {
        return $this->glade;
    }

    public function onAboutClick() {
        $this->glade->get_widget('aboutdialog1')->show();
    }

    public function onFullTextSearchClick() {
        if (!$this->manual) {
            // TODO: One might think aobut using an external browser or the online docs...
            $dialog = new \GtkMessageDialog($this->glade->get_widget('mainwindow'), 0, \Gtk::MESSAGE_ERROR, \Gtk::BUTTONS_OK,
                                  'GtkHTML needed');
            $dialog->set_markup('For doing full text searches GtkHTML support is required in your PHP configuration.');
            $dialog->run();
            $dialog->destroy();
            return;
        }
        $input = trim($this->glade->get_widget('searchentry')->get_text());
        if (strlen($input) == 0) {
            $dialog = new \GtkMessageDialog($this->glade->get_widget('mainwindow'), 0, \Gtk::MESSAGE_ERROR, \Gtk::BUTTONS_OK,
                'No input');
            $dialog->set_markup('No search term entered');
            $dialog->run();
            $dialog->destroy();
            return;
        }
        $results = $this->manual->searchFulltext($input);
        $store = new \GtkTreeStore(\GObject::TYPE_STRING, \GObject::TYPE_PHP_VALUE);
        foreach($results as $title=>$found) {
            $man_container = $store->append(null, array($title, null));
            $basenamelen = strlen('phar://'.$found->getArchiveFileName());
            echo 'phar://'.$found->getArchiveFileName(), "\n";
            foreach($found as $item) {
	        /** @var $item \SplFileObject */
                $doc = \DomDocument::loadHTMLFile($item->getPathname());
                $caption = $doc->getElementsByTagName('title')->item(0)->firstChild->wholeText;
                $store->append($man_container, array($caption, $item));
            }
        }
        $tree = $this->glade->get_widget('searchtreeview');
        $tree->set_model($store);
        $tree->get_selection()->connect('changed', array($this->mainWindow, 'onSearchResultClick')); /* TODO: Move to view  */

        $cell_renderer = new \GtkCellRendererText();
        $colExt = new \GtkTreeViewColumn('', $cell_renderer, 'text', 0);
        $tree->append_column($colExt);
    }

    public function showElementInfo(\Reflector $ref) {
        $this->mainWindow->showDocumentation($ref);
    }

    function showPageFromArchive(\PharfileInfo $ref) {
	$this->mainWindow->showString(file_get_contents($ref->getPathinfo()));
        return;
    }

}

?>
