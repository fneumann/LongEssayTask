<?php
/* Copyright (c) 2021 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\Plugin\LongEssayTask\Writer;

use Edutiek\LongEssayService\Writer\Service;
use ILIAS\Plugin\LongEssayTask\BaseGUI;
use ILIAS\UI\Factory;
use \ilUtil;

/**
 * Start page for writers
 *
 * @package ILIAS\Plugin\LongEssayTask\Writer
 * @ilCtrl_isCalledBy ILIAS\Plugin\LongEssayTask\Writer\WriterStartGUI: ilObjLongEssayTaskGUI
 */
class WriterStartGUI extends BaseGUI
{
    /**
     * Execute a command
     * This should be overridden in the child classes
     * note: permissions are already checked in the object gui
     */
    public function executeCommand()
    {
        $cmd = $this->ctrl->getCmd('showStartPage');
        switch ($cmd)
        {
            case 'showStartPage':
            case 'startWriter':
                $this->$cmd();
                break;

            default:
                $this->tpl->setContent('unknown command: ' . $cmd);
        }
    }


    /**
     * Show the items
     */
    protected function showStartPage()
    {
        $this->toolbar->setFormAction($this->ctrl->getFormAction($this));
        $button = \ilLinkButton::getInstance();
        $button->setUrl('./Customizing/global/plugins/Services/Repository/RepositoryObject/LongEssayTask/lib/editor/index.html');
        $button->setCaption('Bearbeitung starten (Mocklup)', false);
        $button->setPrimary(true);
        $this->toolbar->addButtonInstance($button);

        $button = \ilLinkButton::getInstance();
        $button->setUrl($this->ctrl->getLinkTarget($this, 'startWriter'));
        $button->setCaption('Bearbeitung starten (Service)', false);
        $button->setPrimary(false);
        $this->toolbar->addButtonInstance($button);


        $description = $this->uiFactory->item()->group("Aufgabe", [$this->uiFactory->item()->standard("Beschreibung")
            ->withDescription("Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?")
            ->withProperties(array(
                "Bearbeitung" => "Heute 09:00 - 18:00 Uhr",
                ))])
        ;

        $item1 = $this->uiFactory->item()->standard($this->uiFactory->link()->standard("Informationen zur Prüfung",''))
            ->withLeadIcon($this->uiFactory->symbol()->icon()->standard('file', 'File', 'medium'))
            ->withProperties(array(
                "Filename" => "Informationen.pdf",
                "Verfügbar" => "vorab"));

        $item2 = $this->uiFactory->item()->standard($this->uiFactory->link()->standard("Bürgerliches Gesetzbuch", ''))
            ->withLeadIcon($this->uiFactory->symbol()->icon()->standard('webr', 'Link', 'medium'))
            ->withProperties(array(
                "Webseite" => "https://www.gesetze-im-internet.de/bgb/",
                "Verfügbar" => "vorab"));

        $resources = $this->uiFactory->item()->group("Material", array(
            $item1,
            $item2
        ));

        $page = $this->uiFactory->modal()->lightboxTextPage('Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet, consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum qui dolorem eum fugiat quo voluptas nulla pariatur?', 'Bewertung');
        $modal = $this->uiFactory->modal()->lightbox($page);


        $result = $this->uiFactory->item()->group("Ergebnis", [
            $this->uiFactory->item()->standard("Bestanden")
                ->withDescription("")
                ->withProperties(array(
                "Einsichtnahme" => "01.09.2021 09:00 - 10:00 Uhr"))
                ->withActions($this->uiFactory->dropdown()->standard([
                    $this->uiFactory->button()->shy('Bewertung einsehen', '')
                    ->withOnClick($modal->getShowSignal()),
                    $this->uiFactory->button()->shy('Bewertung herunterladen', '')
                    ]))
            ]);



        $this->tpl->setContent(
            $this->renderer->render($description) .
            $this->renderer->render($resources) .
            $this->renderer->render($result) .
            $this->renderer->render($modal)
        );

     }


    /**
     * Start the Writer Web app
     */
     protected function startWriter()
     {
         $context = new WriterContext();
         $service = new Service($context);
         $service->openFrontend();
     }
}