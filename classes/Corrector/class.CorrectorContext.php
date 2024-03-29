<?php

namespace ILIAS\Plugin\LongEssayTask\Corrector;

use Edutiek\LongEssayService\Corrector\Context;
use Edutiek\LongEssayService\Corrector\Service;
use Edutiek\LongEssayService\Data\CorrectionGradeLevel;
use Edutiek\LongEssayService\Data\CorrectionItem;
use Edutiek\LongEssayService\Data\CorrectionSettings;
use Edutiek\LongEssayService\Data\CorrectionSummary;
use Edutiek\LongEssayService\Data\CorrectionTask;
use Edutiek\LongEssayService\Data\Corrector;
use Edutiek\LongEssayService\Data\WrittenEssay;
use Edutiek\LongEssayService\Exceptions\ContextException;
use ILIAS\Plugin\LongEssayTask\Data\CorrectorSummary;
use ILIAS\Plugin\LongEssayTask\Data\Resource;
use ILIAS\Plugin\LongEssayTask\Data\Writer;
use ILIAS\Plugin\LongEssayTask\ServiceContext;

class CorrectorContext extends ServiceContext implements Context
{
    /**
     * List the availabilities for which resources should be provided in the app
     * @see Resource
     */
    const RESOURCES_AVAILABILITIES = [
        Resource::RESOURCE_AVAILABILITY_BEFORE,
        Resource::RESOURCE_AVAILABILITY_DURING,
        Resource::RESOURCE_AVAILABILITY_AFTER
    ];

    /**
     * id of the selected writer
     * @var ?int
     */
    protected $selected_writer_id;

    protected bool $is_review = false;
    protected bool $is_stitch_decision = false;


    /**
     * Select id of the writer for being corrected
     * The corrector app will load the correction item of this writer
     * @see Writer::getId()
     */
    public function selectWriterId(int $id) : void {
        $this->selected_writer_id = $id;
    }

    /**
     * @inheritDoc
     * here: check if user has the permission to review corrections
     */
    public function setReview(bool $is_review)
    {
        if ($is_review && !$this->object->canMaintainCorrectors()) {
            throw new ContextException('User cannot review corrections', ContextException::PERMISSION_DENIED);
        }
        $this->is_review = $is_review;
    }

    /**
     * @inheritDoc
     */
    public function isReview(): bool
    {
        return $this->is_review;
    }

    /**
     * @inheritDoc
     * here: check if user has the permission to draw stitch decisions
     */
    public function setStitchDecision(bool $is_stitch_decision)
    {
        if ($is_stitch_decision && !$this->object->canMaintainCorrectors()) {
            throw new ContextException('User cannot draw stitch decisions', ContextException::PERMISSION_DENIED);
        }
        $this->is_stitch_decision = $is_stitch_decision;
    }

    /**
     * @inheritDoc
     */
    public function isStitchDecision(): bool
    {
        return $this->is_stitch_decision;
    }


    /**
     * @inheritDoc
     * here: support a separate url from the plugin config (for development purposes)
     */
    public function getFrontendUrl(): string
    {
        $config = $this->plugin->getConfig();

        if (!empty($config->getCorrectorUrl())) {
            return $config->getCorrectorUrl();
        }
        else {
            return  ILIAS_HTTP_PATH
                . "/Customizing/global/plugins/Services/Repository/RepositoryObject/LongEssayTask"
                . "/vendor/edutiek/long-essay-service"
                . "/" . Service::FRONTEND_RELATIVE_PATH;
        }
    }

    /**
     * @inheritDoc
     * here: URL of the corrector_service script
     */
    public function getBackendUrl(): string
    {
        return  ILIAS_HTTP_PATH
            . "/Customizing/global/plugins/Services/Repository/RepositoryObject/LongEssayTask/corrector_service.php"
            . "?client_id=" . CLIENT_ID;
    }

    /**
     * @inheritDoc
     * here: get the link to the repo object
     * The ILIAS session still has to exist, otherwise the user has to log in again
     */
    public function getReturnUrl(): string
    {
        if ($this->isReview() || $this->isStitchDecision()) {
            return \ilLink::_getStaticLink($this->object->getRefId(), 'xlet', true, '_correctoradmin');
        }
        return \ilLink::_getStaticLink($this->object->getRefId(), 'xlet', true, '_corrector');
    }

    /**
     * @inheritDoc
     */
    public function getCorrectionTask(): CorrectionTask
    {
        return new CorrectionTask(
			(string) $this->object->getTitle(),
			(string) $this->task->getInstructions(),
            $this->data->dbTimeToUnix($this->task->getCorrectionEnd()));
    }

    /**
     * @inheritDoc
     */
    public function getCorrectionSettings(): CorrectionSettings
    {
        if (!empty($repoSettings = $this->localDI->getTaskRepo()->getCorrectionSettingsById($this->task->getTaskId()))) {
            return new CorrectionSettings(
                (bool) $repoSettings->getMutualVisibility(),
                (bool) $repoSettings->getMultiColorHighlight(),
                (int) $repoSettings->getMaxPoints(),
                (float) $repoSettings->getMaxAutoDistance(),
                (bool) $repoSettings->getStitchWhenDistance(),
                (bool) $repoSettings->getStitchWhenDecimals()
            );
        }
        return new CorrectionSettings(false, false, 0, 0, false, false);
    }

    /**
     * @inheritDoc
     */
    public function getGradeLevels(): array
    {
        $objectRepo = $this->localDI->getObjectRepo();

        $levels = [];
        foreach ($objectRepo->getGradeLevelsByObjectId($this->object->getId()) as $repoLevel) {
            $levels[] = new CorrectionGradeLevel(
                (string) $repoLevel->getId(),
                $repoLevel->getGrade(),
                $repoLevel->getMinPoints()
            );
        }
        return $levels;
    }

    /**
     * @inheritDoc
     * here:    the item keys are strings of the writer ids
     *          the item titles are the pseudonymous writer names
     */
    public function getCorrectionItems(bool $use_filter = false): array
    {
        if ($this->isStitchDecision()) {
            return $this->getCorrectionItemsForStitchDecision();
        }
        elseif ($this->isReview()) {
            return $this->getCorrectionItemsForReview();
        }
        else {
            return $this->getCorrectionItemsForCorrector($use_filter);
        }
    }

    /**
     * Get the correction items that can be reviewed
     */
    protected function getCorrectionItemsForReview() : array
    {
        $essayRepo = $this->localDI->getEssayRepo();
        $writerRepo = $this->localDI->getWriterRepo();

        $items = [];
        $essays = $essayRepo->getEssaysByTaskId($this->object->getId());
        foreach ($essays as $essay) {
            if(!empty($essay->getWritingAuthorized())) {
                $repoWriter = $writerRepo->getWriterById($essay->getWriterId());
                $items[] = new CorrectionItem(
                    (string) $essay->getWriterId(),
                    (string) $repoWriter->getPseudonym(),
                    false,
                    false
                );
            }
        }
        return $items;
    }


    /**
     * Get the correction items that need a stitch decision
     */
    protected function getCorrectionItemsForStitchDecision() : array
    {
        $essayRepo = $this->localDI->getEssayRepo();
        $writerRepo = $this->localDI->getWriterRepo();
        $adminService = $this->localDI->getCorrectorAdminService($this->object->getId());

        $items = [];
        $essays = $essayRepo->getEssaysByTaskId($this->object->getId());
        foreach ($essays as $essay){
            if($adminService->isStitchDecisionNeeded($essay)) {
                $repoWriter = $writerRepo->getWriterById($essay->getWriterId());
                $items[] = new CorrectionItem(
                    (string) $essay->getWriterId(),
                    (string) $repoWriter->getPseudonym(),
                    false,
                    false
                );
            }
        }
        return $items;
    }


    /**
     * Get the correction items for a corrector
     * @param bool $use_filter    apply a user filter
     * @todo: merge with CorrectorStartGUI::getItems() in a CorrectorAdminService function
     */
    protected function getCorrectionItemsForCorrector(bool $use_filter = false): array
    {
        $correctorRepo = $this->localDI->getCorrectorRepo();
        $writerRepo = $this->localDI->getWriterRepo();
        $essayRepo = $this->localDI->getEssayRepo();

        $sort_list = [];
        if (!empty($repoCorrector = $correctorRepo->getCorrectorByUserId($this->user->getId(), $this->task->getTaskId()))) {
            foreach ($correctorRepo->getAssignmentsByCorrectorId($repoCorrector->getId()) as $repoAssignment) {
                if (!empty($repoWriter = $writerRepo->getWriterById($repoAssignment->getWriterId()))) {
                    $repoEssay = $essayRepo->getEssayByWriterIdAndTaskId($repoAssignment->getWriterId(), $this->task->getTaskId());
                    if (empty($repoEssay)) {
                        continue;
                    }

                    $authorization_allowed = true;
                    foreach ($correctorRepo->getAssignmentsByWriterId($repoWriter->getId()) as $otherAssignment) {
                        if ($otherAssignment->getCorrectorId() == $repoAssignment->getCorrectorId()
                            || $otherAssignment->getPosition() >= $repoAssignment->getPosition()) {
                            continue;
                        }
                        $otherSummary = $essayRepo->getCorrectorSummaryByEssayIdAndCorrectorId($repoEssay->getId(), $otherAssignment->getCorrectorId());
                        if (empty($otherSummary) || empty($otherSummary->getCorrectionAuthorized())) {
                            $authorization_allowed = false;
                        }
                    }

                    $title = $repoWriter->getPseudonym() . ' (' . $this->data->formatCorrectorPosition($repoAssignment) . ')';
                    if (empty($repoEssay->getWritingAuthorized()) || !empty($repoEssay->getWritingExcluded())) {
                        $title .= ' - ' . $this->data->formatWritingStatus($repoEssay, false);
                    }

					$summary = $this->localDI->getEssayRepo()->getCorrectorSummaryByEssayIdAndCorrectorId(
						$repoEssay->getId(), $repoCorrector->getId());

                    $sort_list[] = [
                        'item' => new CorrectionItem(
                            (string) $repoWriter->getId(),
                            $title,
                            true,
                            $authorization_allowed
                        ),
                        'position' => $repoAssignment->getPosition(),
                        'pseudonym' => $repoWriter->getPseudonym(),
						'correction_status' => $this->data->getOwnCorrectionStatus($repoEssay, $summary)
                    ];
                }
            }
        }
		$admin_service = $this->localDI->getCorrectorAdminService($this->object->getId());
		$admin_service->sortCorrectionsArray($sort_list);

        if ($use_filter) {
            $filtered_list = $admin_service->filterCorrections($repoCorrector->getUserId(), $sort_list);
            if(!empty($filtered_list)){ // If no Corrections where found due to filtering use all corrections
                $sort_list = $filtered_list;
            }
        }

        $items = [];
        foreach ($sort_list as $sort_item) {
            $items[] = $sort_item['item'];
        }
        return $items;
    }

    /**
     * @inheritDoc
     * here:    the corrector key is a string of the corrector id
     */
    public function getCurrentCorrector(): ?Corrector
    {
        $correctorRepo = $this->localDI->getCorrectorRepo();
        if (!empty($repoCorrector = $correctorRepo->getCorrectorByUserId($this->user->getId(), $this->task->getTaskId()))) {
            return new Corrector(
                (string) $repoCorrector->getId(),
                $this->user->getFullname()
            );
        }
        return null;
    }

    /**
     * @inheritDoc
     * here:    the item key is a string of the writer id
     *          the item title is the pseudonymous writer name
     */
    public function getCurrentItem(): ?CorrectionItem
    {
        $essayRepo = $this->localDI->getEssayRepo();
        $correctorRepo = $this->localDI->getCorrectorRepo();
        $writerRepo = $this->localDI->getWriterRepo();

        if (isset($this->selected_writer_id)) {
            if ($this->isReview() || $this->isStitchDecision()) {
                if (!empty($repoEssay = $essayRepo->getEssayByWriterIdAndTaskId($this->selected_writer_id, $this->task->getTaskId()))) {
                    $repoWriter = $writerRepo->getWriterById($repoEssay->getWriterId());
                    return new CorrectionItem(
                        (string) $repoEssay->getWriterId(),
                        $repoWriter->getPseudonym(),
                        false,
                        false
                    );
                }
            }
            if (!empty($repoCorrector = $correctorRepo->getCorrectorByUserId($this->user->getId(), $this->task->getTaskId())) &&
                !empty($repoWriter = $writerRepo->getWriterById((int) $this->selected_writer_id)))
            {
                foreach ($correctorRepo->getAssignmentsByWriterId((int) $this->selected_writer_id) as $assignment) {
                    if ($assignment->getCorrectorId() == $repoCorrector->getId()) {
                        return new CorrectionItem(
                            (string) $repoWriter->getId(),
                            $repoWriter->getPseudonym()
                        );
                    }
                }
            }
        }
        return null;
     }

    /**
     * @inheritDoc
     * here:    the item key is a string of the writer id
     */
    public function getEssayOfItem(string $item_key): ?WrittenEssay
    {
        if (!empty($repoEssay = $this->localDI->getEssayRepo()->getEssayByWriterIdAndTaskId(
                (int) $item_key, $this->task->getTaskId()))) {
            $writtenEssay = new WrittenEssay(
                $repoEssay->getWrittenText(),
                $repoEssay->getRawTextHash(),
                $repoEssay->getProcessedText(),
                $this->data->dbTimeToUnix($repoEssay->getEditStarted()),
                $this->data->dbTimeToUnix($repoEssay->getEditEnded()),
                !empty($repoEssay->getWritingAuthorized()),
                $this->data->dbTimeToUnix($repoEssay->getWritingAuthorized()),
                !empty($repoEssay->getWritingAuthorized()) ? \ilObjUser::_lookupFullname($repoEssay->getWritingAuthorizedBy()) : null
            );

            if ($repoEssay->getCorrectionFinalized()) {
                $writtenEssay = $writtenEssay
                    ->withCorrectionFinalized($this->data->dbTimeToUnix($repoEssay->getCorrectionFinalized()))
                    ->withCorrectionFinalizedBy(\ilObjUser::_lookupFullname($repoEssay->getCorrectionFinalizedBy()))
                    ->withFinalPoints($repoEssay->getFinalPoints())
                    ->withFinalGrade($this->localDI->getObjectRepo()->ifGradeLevelExistsById((int) $repoEssay->getFinalGradeLevelId()) ?
                        $this->localDI->getObjectRepo()->getGradeLevelById((int) $repoEssay->getFinalGradeLevelId())->getGrade() : '')
                    ->withStitchComment($repoEssay->getStitchComment());
            }

            return $writtenEssay;
        }
        return null;
    }

    /**
     * @inheritDoc
     * here:    the item key is a string of the writer id
     *          the corrector keys are strings of the corrector ids
     *          the corrector titles are the usernames of the correctors
     */
    public function getCorrectorsOfItem(string $item_key): array
    {
       $add_others = true;
       if (empty($correctionSettings = $this->localDI->getTaskRepo()->getCorrectionSettingsById($this->task->getTaskId()))
           || $correctionSettings->getMutualVisibility() == 0 ) {
           $add_others = false;
       }

       $currentCorrector = $this->getCurrentCorrector();
       $correctorRepo = $this->localDI->getCorrectorRepo();
       $correctors = [];
       foreach ($correctorRepo->getAssignmentsByWriterId((int) $item_key) as $assignment) {
            if (!empty($repoCorrector = $correctorRepo->getCorrectorById($assignment->getCorrectorId()))) {
                $corrector = new Corrector(
                    (string) $repoCorrector->getId(),
                    \ilObjUser::_lookupFullname($repoCorrector->getUserId())
                );

                if ($add_others || $corrector->getKey() == $currentCorrector->getKey()){
                    $correctors[] = $corrector;
                }
            }
        }
        return $correctors;
    }

    /**
     * @inheritDoc
     * here:    the item key is a string of the writer id
     *          the corrector key is a string of the corrector id
     */
    public function getCorrectionSummary(string $item_key, string $corrector_key): ?CorrectionSummary
    {
        $repoCorrector = $this->localDI->getCorrectorRepo()->getCorrectorById((int) $corrector_key);
        $essayRepo = $this->localDI->getEssayRepo();
        if (!empty($repoEssay = $essayRepo->getEssayByWriterIdAndTaskId((int) $item_key, $this->task->getTaskId()))) {
            if (!empty($repoSummary = $essayRepo->getCorrectorSummaryByEssayIdAndCorrectorId(
                $repoEssay->getId(), $repoCorrector->getId())
            )) {
                return new CorrectionSummary(
                    $repoSummary->getSummaryText(),
                    (float) $repoSummary->getPoints(),
                    $repoSummary->getGradeLevelId() ? (string) $repoSummary->getGradeLevelId() : null,
                    $this->data->dbTimeToUnix($repoSummary->getLastChange()),
                    !empty($repoSummary->getCorrectionAuthorized()),
                    \ilObjUser::_lookupFullname($repoCorrector->getUserId()),
                    $this->localDI->getObjectRepo()->ifGradeLevelExistsById((int) $repoSummary->getGradeLevelId()) ?
                        $this->localDI->getObjectRepo()->getGradeLevelById((int) $repoSummary->getGradeLevelId())->getGrade() : ''
                );
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     * here:    the item key is a string of the writer id
     */

    public function setProcessedText(string $item_key, ?string $text) : void
    {
        $essayRepo = $this->localDI->getEssayRepo();
        if (!empty($repoEssay = $essayRepo->getEssayByWriterIdAndTaskId((int)$item_key, $this->task->getTaskId()))) {
            $repoEssay->setProcessedText($text);
            $essayRepo->updateEssay($repoEssay);
        }
    }

    /**
     * @inheritDoc
     * here:    the item key is a string of the writer id
     *          the corrector key is a string of the corrector id
     */
    public function setCorrectionSummary(string $item_key, string $corrector_key, CorrectionSummary $summary) : void
    {
        $service = $this->localDI->getCorrectorAdminService($this->task->getTaskId());
        $essayRepo = $this->localDI->getEssayRepo();

        if (!empty($repoEssay = $essayRepo->getEssayByWriterIdAndTaskId((int) $item_key, $this->task->getTaskId()))) {

            $repoSummary = $essayRepo->getCorrectorSummaryByEssayIdAndCorrectorId($repoEssay->getId(), (int) $corrector_key);
            if (!isset($repoSummary)) {
                $repoSummary = new CorrectorSummary();
                $repoSummary->setEssayId($repoEssay->getId());
                $repoSummary->setCorrectorId((int) $corrector_key);
                $essayRepo->createCorrectorSummary($repoSummary);
            }
            $repoSummary->setSummaryText($summary->getText());
            $repoSummary->setPoints($summary->getPoints());
            $repoSummary->setGradeLevelId($summary->getGradeKey() ? (int) $summary->getGradeKey() : null);
            $repoSummary->setLastChange($this->data->unixTimeToDb($summary->getLastChange()));

            if ($summary->isAuthorized()) {
                if (empty($repoSummary->getCorrectionAuthorized())) {
                    $repoSummary->setCorrectionAuthorized($this->data->unixTimeToDb(time()));
                }
                if (empty($repoSummary->getCorrectionAuthorizedBy())) {
                    $repoSummary->setCorrectionAuthorizedBy($this->user->getId());
                }
                $essayRepo->updateCorrectorSummary($repoSummary);
                $service->tryFinalisation($repoEssay, $this->user->getId());
            }
            else {
                $repoSummary->setCorrectionAuthorized(null);
                $repoSummary->setCorrectionAuthorizedBy(null);
                $essayRepo->updateCorrectorSummary($repoSummary);
            }

        }
    }

    /**
     * @inheritDoc
     */
    public function saveStitchDecision(string $item_key, int $timestamp, ?float $points, ?string $grade_key, ?string $stitch_comment) : bool
    {
        $essayRepo = $this->localDI->getEssayRepo();
        $dataService = $this->localDI->getDataService($this->task->getTaskId());

        if (!empty($repoEssay = $essayRepo->getEssayByWriterIdAndTaskId((int) $item_key, $this->task->getTaskId()))
            && empty($repoEssay->getCorrectionFinalized())) {
            $repoEssay->setFinalPoints($points);
            $repoEssay->setFinalGradeLevelId($grade_key ? (int) $grade_key : null);
            $repoEssay->setCorrectionFinalized($dataService->unixTimeToDb($timestamp));
            $repoEssay->setCorrectionFinalizedBy($this->user->getId());
            $repoEssay->setStitchComment($stitch_comment);
            $essayRepo->updateEssay($repoEssay);
            return true;
        }
        return false;
    }
}
