<?php
/* Copyright (c) 2021 ILIAS open source, Extended GPL, see docs/LICENSE */

use ILIAS\DI\Container;
use ILIAS\Plugin\LongEssayTask\CorrectorAdmin\CorrectorAdminService;
use ILIAS\Plugin\LongEssayTask\Data\CorrectionSettings;
use ILIAS\Plugin\LongEssayTask\Data\DataService;
use ILIAS\Plugin\LongEssayTask\Data\EditorSettings;
use ILIAS\Plugin\LongEssayTask\Data\ObjectSettings;
use ILIAS\Plugin\LongEssayTask\Data\PluginConfig;
use ILIAS\Plugin\LongEssayTask\Data\TaskSettings;
use ILIAS\Plugin\LongEssayTask\LongEssayTaskDI;

/**
 * Repository object
 * @author Fred Neumann <fred.neumann@ilias.de>
 */
class ilObjLongEssayTask extends ilObjectPlugin
{
    /** @var Container */
    protected $dic;

    /** @var ilAccess */
    protected $access;

    /** @var ilObjUser */
    protected $user;

    /** @var ObjectSettings */
    protected $objectSettings;

    /** @var TaskSettings */
    protected $taskSettings;

    /** @var LongEssayTaskDI */
    protected $localDI;

    /** @var ilLongEssayTaskPlugin */
    protected $plugin;

    /** @var DataService  */
    protected $data;

    /**
	 * Constructor
	 *
	 * @access        public
	 * @param int $a_ref_id
	 */
	function __construct($a_ref_id = 0)
	{
	    global $DIC;
	    $this->dic = $DIC;
        $this->access = $DIC->access();
        $this->user = $DIC->user();
        $this->localDI = LongEssayTaskDI::getInstance();
        $this->plugin = ilLongEssayTaskPlugin::getInstance();

		parent::__construct($a_ref_id);

        $this->data = new DataService($this);
	}


	/**
	 * Get type.
	 */
	final function initType()
	{
		$this->setType(ilLongEssayTaskPlugin::ID);
	}

	/**
	 * Create object
	 */
	protected function doCreate()
	{
        $di = LongEssayTaskDI::getInstance();
        $object_repo = $di->getObjectRepo();
        $task_repo = $di->getTaskRepo();

        $new_obj_settings = new ObjectSettings($this->getId());
        $new_plugin_settings = new PluginConfig($this->getId());
        $new_task_settings = new TaskSettings($this->getId());
        $new_editor_settings = new EditorSettings($this->getId());
        $new_correction_settings = new CorrectionSettings($this->getId());

        $object_repo->createObject($new_obj_settings, $new_plugin_settings);
        $task_repo->createTask($new_task_settings, $new_editor_settings, $new_correction_settings);
        $this->objectSettings = $new_obj_settings;
        $this->taskSettings = $new_task_settings;
	}

	/**
	 * Read data from db
	 */
    protected function doRead()
	{
        $this->objectSettings = $this->localDI->getObjectRepo()->getObjectSettingsById($this->getId());
        $this->taskSettings = $this->localDI->getTaskRepo()->getTaskSettingsById($this->getId());
	}

	/**
	 * Update data
	 */
    protected function doUpdate()
	{
        $this->localDI->getObjectRepo()->updateObjectSettings($this->objectSettings);
	}

	/**
	 * Delete data from db
	 */
    protected function doDelete()
	{
        //$di = LongEssayTaskDI::getInstance();
        //$object_repo = $di->getObjectRepo();

        //$object_repo->deleteObject($this->getId());
	}

	/**
	 * Do Cloning
     * @param self $new_obj
     * @param int $a_target_id
     * @param int|null $a_copy_id
	 */
    protected function doCloneObject($new_obj, $a_target_id, $a_copy_id = null)
	{
        $di = LongEssayTaskDI::getInstance();
        $object_repo = $di->getObjectRepo();
        $task_repo = $di->getTaskRepo();

        //Cloning Area
		$new_obj->objectSettings = clone $this->objectSettings;
        $new_obj_settings = $new_obj->objectSettings->setObjId($new_obj->getId());

        $new_plugin_settings = clone $object_repo->getPluginConfigById($this->getId());
        $new_task_settings = clone $task_repo->getTaskSettingsById($this->getId());
        $new_editor_settings = clone $task_repo->getEditorSettingsById($this->getId());
        $new_correction_settings = clone $task_repo->getCorrectionSettingsById($this->getId());

        $old_grade_level = $object_repo->getGradeLevelsByObjectId($this->getId());
        $new_grade_level = [];
        foreach($old_grade_level as $grade_level)
        {
            if ($grade_level instanceof \ILIAS\Plugin\LongEssayTask\Data\GradeLevel)
            {
                $new_grade_level[] = (clone $grade_level)->setId($this->getId());
            }
        }

        $old_rating_criterion = $object_repo->getRatingCriterionByObjectId($new_obj->getId());
        $new_rating_criterion = [];
        foreach($old_rating_criterion as $rating_criterion)
        {
            if ($rating_criterion instanceof \ILIAS\Plugin\LongEssayTask\Data\RatingCriterion)
            {
                $new_rating_criterion[] = (clone $rating_criterion)->setId($new_obj->getId());
            }
        }
        //TODO: Add Objects from TaskRepo to clone

        // Creation Area
        $object_repo->updateObjectSettings($new_obj_settings);
        $object_repo->updatePluginConfig($new_plugin_settings->setId($new_obj->getId()));

        $task_repo->updateTaskSettings($new_task_settings->setTaskId($new_obj->getId()));
        $task_repo->updateEditorSettings($new_editor_settings->setTaskId($new_obj->getId()));
        $task_repo->updateCorrectionSettings($new_correction_settings->setTaskId($new_obj->getId()));

        foreach($new_grade_level as $grade_level)
        {
            $object_repo->createGradeLevel($grade_level);
        }

        foreach($new_rating_criterion as $rating_criterion)
        {
            $object_repo->createRatingCriterion($rating_criterion);
        }
	}

	/**
	 * Set online
	 *
	 * @param boolean $a_val
	 */
	public function setOnline($a_val)
	{
		$this->objectSettings->setOnline($a_val);
	}

    /**
     * Set the Participation Type
     * @param string $a_type
     */
	public function setParticipationType($a_type)
    {
        $this->objectSettings->setParticipationType($a_type);
    }

    /**
     * Get the Participation Type
     * @return string
     */
    public function getParticipationType()
    {
        return $this->objectSettings->getParticipationType();
    }

    /**
	 * Get online
	 * @return bool
	 */
    public function isOnline()
	{
		return (bool) $this->objectSettings->isOnline();
	}

    /**
     * Get the plugin object
     * @return object
     */
	public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * Check if the current user can view the
     */
    public function canViewWriterScreen()
    {
        return $this->access->checkAccess('read', '', $this->getRefId());
//            && !$this->localDI->getCorrectorRepo()->ifUserExistsInTaskAsCorrector($this->user->getId(), $this->getId());
    }

    /**
     * Check if the current user can view the
     */
    public function canViewCorrectorScreen()
    {
        return $this->access->checkAccess('read', '', $this->getRefId());
//            && $this->localDI->getCorrectorRepo()->ifUserExistsInTaskAsCorrector($this->user->getId(), $this->getId());
    }


    /**
     * Check if the current user can edit the organisational settings (online, dates)
     */
    public function canEditOrgaSettings()
    {
        return $this->access->checkAccess('write', '', $this->getRefId());
    }

    /**
     *Check if the user can edit additional material
     */
    public function canEditTechnicalSettings()
    {
        return $this->access->checkAccess('write', '', $this->getRefId());
    }

    /**
     *Check if the user can edit the content settings
     */
    public function canEditContentSettings()
    {
        return $this->access->checkAccess('maintain_task', '', $this->getRefId());
    }

    /**
     *Check if the user can edit the grades
     */
    public function canEditGrades()
    {
        return $this->access->checkAccess('maintain_task', '', $this->getRefId());
    }

    /**
     *Check if the user can edit the criteria
     */
    public function canEditCriteria()
    {
        return $this->access->checkAccess('maintain_task', '', $this->getRefId());
    }

    /**
     *Check if the user can edit additional material
     */
    public function canEditMaterial()
    {
        return $this->access->checkAccess('maintain_task', '', $this->getRefId());
    }

    /**
     *Check if the user can maintain the writers
     */
    public function canMaintainWriters() {
        return $this->access->checkAccess('maintain_writers', '', $this->getRefId());
    }

    /**
     *Check if the user can maintain the writers
     */
    public function canMaintainCorrectors() {
        return $this->access->checkAccess('maintain_correctors', '', $this->getRefId());
    }

    /**
     * Check if the user can write the essay
     */
    public function canWrite() : bool
    {
        if (!$this->canViewWriterScreen()) {
            return false;
        }

        // todo: respect time extension
        if (!$this->data->isInRange(time(),
            $this->data->dbTimeToUnix($this->taskSettings->getWritingStart()),
            $this->data->dbTimeToUnix($this->taskSettings->getWritingEnd()))) {
            return false;
        }

        // check if not authorized
        if (!empty($writer = $this->localDI->getWriterRepo()->getWriterByUserId(
            $this->dic->user()->getId(), $this->taskSettings->getTaskId()))) {
            if (!empty($essay = $this->localDI->getEssayRepo()->getEssayByWriterIdAndTaskId(
                $writer->getId(), $this->taskSettings->getTaskId()
            ))) {
                if (!empty($essay->getWritingAuthorized())) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if the user can review the essay
     */
    public function canReview() : bool
    {
        if (!$this->canViewWriterScreen()) {
            return false;
        }

        if (!$this->data->isInRange(time(),
            $this->data->dbTimeToUnix($this->taskSettings->getReviewStart()),
            $this->data->dbTimeToUnix($this->taskSettings->getReviewEnd()))) {
            return false;
        }

        return true;
    }

    /**
     * Get the service for handling object data
     */
    public function getDataService() : DataService
    {
        return $this->data;
    }

    /**
     * Get the service for maintaining correctors
     */
    public function getCorrectorAdminService() : CorrectorAdminService
    {
        return new CorrectorAdminService($this);
    }
}
