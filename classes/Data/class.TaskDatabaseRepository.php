<?php

namespace ILIAS\Plugin\LongEssayTask\Data;

use Exception;

/**
 * @author Fabian Wolf <wolf@ilias.de>
 */
class TaskDatabaseRepository implements TaskRepository
{
	private \ilDBInterface $database;
	private EssayRepository $essay_repo;
	private CorrectorRepository $corrector_repo;
	private WriterRepository $writer_repo;

	public function __construct(\ilDBInterface $database,
								EssayRepository $essay_repo,
								CorrectorRepository $corrector_repo,
								WriterRepository $writer_repo)
	{
		$this->database = $database;
		$this->essay_repo = $essay_repo;
		$this->corrector_repo = $corrector_repo;
		$this->writer_repo = $writer_repo;
	}


	public function createTask(TaskSettings $a_task_settings, EditorSettings $a_editor_settings, CorrectionSettings $a_correction_settings)
    {
        $a_task_settings->create();
        $a_editor_settings->create();
        $a_correction_settings->create();
    }

    public function createAlert(Alert $a_alert)
    {
        $a_alert->create();
    }

    public function createWriterNotice(WriterNotice $a_writer_notice)
    {
        $a_writer_notice->create();
    }

    public function createResource(Resource $a_resource)
    {
        $a_resource->create();
    }

    public function getEditorSettingsById(int $a_id): ?EditorSettings
    {
        $editor_settings = EditorSettings::findOrGetInstance($a_id);
        if ($editor_settings != null) {
            return $editor_settings;
        }
        return null;
    }

    public function getCorrectionSettingsById(int $a_id): ?CorrectionSettings
    {
        $correction_settings = CorrectionSettings::findOrGetInstance($a_id);
        if ($correction_settings != null) {
            return $correction_settings;
        }
        return null;
    }



    public function ifTaskExistsById(int $a_id): bool
    {
        return $this->getTaskSettingsById($a_id) != null;
    }

    public function getTaskSettingsById(int $a_id): ?TaskSettings
    {
        $task_settings = TaskSettings::findOrGetInstance($a_id);
        if ($task_settings != null) {
            return $task_settings;
        }
        return null;
    }

    public function ifAlertExistsById(int $a_id): bool
    {
        return $this->getAlertById($a_id) != null;
    }

    public function getAlertById(int $a_id): ?Alert
    {
        $alert = Alert::findOrGetInstance($a_id);
        if ($alert != null) {
            return $alert;
        }
        return null;
    }

    public function ifWriterNoticeExistsById(int $a_id): bool
    {
        return $this->getWriterNoticeById($a_id) != null;
    }

	public function getWriterNoticeByTaskId(int $a_task_id): array
	{
		return WriterNotice::where(['task_id' => $a_task_id])->get();
	}

    public function getWriterNoticeById(int $a_id): ?WriterNotice
    {
        $writer_notice = WriterNotice::findOrGetInstance($a_id);
        if ($writer_notice != null) {
            return $writer_notice;
        }
        return null;
    }

    public function updateEditorSettings(EditorSettings $a_editor_settings)
    {
        $a_editor_settings->update();
    }

    public function updateCorrectionSettings(CorrectionSettings $a_correction_settings)
    {
        $a_correction_settings->update();
    }

    public function updateTaskSettings(TaskSettings $a_task_settings)
    {
        $a_task_settings->update();
    }

    public function updateAlert(Alert $a_alert)
    {
        $a_alert->update();
    }

    public function updateWriterNotice(WriterNotice $a_writer_notice)
    {
        $a_writer_notice->update();
    }

    /**
     * Deletes TaskSettings, EditorSettings, CorrectionSettings, Resources, Alerts, WriterNotices and Essay related datasets by Task ID
     *
     * @param int $a_id
     * @throws Exception
     */
    public function deleteTask(int $a_id)
    {
        $this->database->manipulate("DELETE FROM xlet_task_settings" .
            " WHERE task_id = " . $this->database->quote($a_id, "integer"));
		$this->database->manipulate("DELETE FROM xlet_editor_settings" .
            " WHERE task_id = " . $this->database->quote($a_id, "integer"));
		$this->database->manipulate("DELETE FROM xlet_corr_setting" .
            " WHERE task_id = " . $this->database->quote($a_id, "integer"));

        $this->deleteAlertByTaskId($a_id);
        $this->deleteWriterNoticeByTaskId($a_id);
        $this->deleteResourceByTaskId($a_id);
		$this->deleteLogEntryByTaskId($a_id);

		$this->essay_repo->deleteEssayByTaskId($a_id);
		$this->corrector_repo->deleteCorrectorByTask($a_id);
		$this->writer_repo->deleteWriter($a_id);
    }

    public function deleteAlertByTaskId(int $a_task_id)
    {
		$this->database->manipulate("DELETE FROM xlet_alert" .
            " WHERE task_id = " . $this->database->quote($a_task_id, "integer"));
    }

    public function deleteWriterNoticeByTaskId(int $a_task_id)
    {
		$this->database->manipulate("DELETE FROM xlet_writer_notice" .
            " WHERE task_id = " . $this->database->quote($a_task_id, "integer"));
    }

    public function deleteAlert(int $a_id)
    {
		$this->database->manipulate("DELETE FROM xlet_alert" .
            " WHERE id = " . $this->database->quote($a_id, "integer"));
    }

    public function deleteWriterNotice(int $a_id)
    {
		$this->database->manipulate("DELETE FROM xlet_writer_notice" .
            " WHERE id = " . $this->database->quote($a_id, "integer"));
    }

    /**
     * Deletes TaskSettings, EditorSettings, CorrectionSettings, Alerts and WriterNotices by Object ID
     *
     * @param int $a_object_id
     */
    public function deleteTaskByObjectId(int $a_object_id)
    {
		$this->deleteTask($a_object_id);
    }

    public function getResourceById(int $a_id): ?Resource
    {
        $resource = Resource::findOrGetInstance($a_id);
        if ($resource != null) {
            return $resource;
        }
        return null;
    }

    public function getResourceByTaskId(int $a_task_id): array
    {
        return Resource::where(['task_id' => $a_task_id])->get();
    }

    public function ifResourceExistsById(int $a_id): bool
    {
        return $this->getResourceById($a_id) != null;
    }

    public function updateResource(Resource $a_resource)
    {
        $a_resource->update();
    }

    public function deleteResource(int $a_id)
    {
		$this->database->manipulate("DELETE FROM xlet_resource" .
            " WHERE id = " . $this->database->quote($a_id, "integer"));
    }

    public function deleteResourceByTaskId(int $a_task_id)
    {
		$this->database->manipulate("DELETE FROM xlet_resource" .
            " WHERE task_id = " . $this->database->quote($a_task_id, "integer"));
    }

    public function getResourceByFileId(string $a_file_id): ?Resource
    {
        $resource =Resource::where(['file_id' => $a_file_id])->get();

        if (count($resource) > 0) {
            return $resource[0];
        }
        return null;
    }

    public function ifResourceExistsByFileId(string $a_file_id): bool
    {
        return $this->getResourceByFileId($a_file_id) != null;
    }


	public function createLogEntry(LogEntry $a_log_entry)
	{
		$a_log_entry->create();
	}

	public function ifLogEntryExistsById(int $a_id): bool
	{
		return $this->getLogEntryById($a_id) != null;
	}

	public function getLogEntryById(int $a_id): ?LogEntry
	{
		$log_entry = LogEntry::findOrGetInstance($a_id);
		if ($log_entry != null) {
			return $log_entry;
		}
		return null;
	}

	public function updateLogEntry(LogEntry $a_log_entry)
	{
		$a_log_entry->update();
	}

	public function deleteLogEntry(int $a_id)
	{
		$this->database->manipulate("DELETE FROM xlet_log_entry" .
			" WHERE id = " . $this->database->quote($a_id, "integer"));
	}

	public function deleteLogEntryByTaskId(int $a_task_id)
	{
		$this->database->manipulate("DELETE FROM xlet_log_entry" .
			" WHERE task_id = " . $this->database->quote($a_task_id, "integer"));
	}

	public function getLogEntriesByTaskId(int $a_task_id): array
	{
		return LogEntry::where(['task_id' => $a_task_id])->get();
	}

	public function getAlertsByTaskId(int $a_task_id): array
	{
		return Alert::where(['task_id' => $a_task_id])->get();
	}
}