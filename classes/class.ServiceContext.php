<?php

namespace ILIAS\Plugin\LongEssayTask;

use Edutiek\LongEssayService\Base\BaseContext;
use Edutiek\LongEssayService\Data\ApiToken;
use Edutiek\LongEssayService\Data\EnvResource;
use Edutiek\LongEssayService\Data\WritingTask;
use Edutiek\LongEssayService\Exceptions\ContextException;
use ILIAS\Plugin\LongEssayTask\Data\AccessToken;
use ILIAS\Plugin\LongEssayTask\Data\DataService;
use ILIAS\Plugin\LongEssayTask\Data\Resource;
use ILIAS\Plugin\LongEssayTask\Data\TaskSettings;
use ilContext;
use \ilObjUser;
use \ilObject;
use \ilObjLongEssayTask;
use ilSession;

abstract class ServiceContext implements BaseContext
{
    /**
     * List the availabilities for which resources should be provided in the app
     * @see Resource
     */
    const RESOURCES_AVAILABILITIES = [
        // override this for writer and corrector context
    ];


    /** @var \ilLongEssayTaskPlugin */
    protected $plugin;

    /** @var LongEssayTaskDI */
    protected $localDI;

    /** @var ilObjLongEssayTask */
    protected $object;

    /** @var ilObjUser */
    protected $user;

    /** @var TaskSettings */
    protected $task;

    /** @var DataService */
    protected $data;

    /**
     * @inheritDoc
     */
    function __construct()
    {
        $this->plugin = \ilLongEssayTaskPlugin::getInstance();
        $this->localDI = LongEssayTaskDI::getInstance();
    }

    /**
     * @inheritDoc
     * here: use string versions of the user id and ref_id of the repository object
     */
    public function init(string $user_key, string $environment_key): void
    {
        $user_id = (int) $user_key;
        $ref_id = (int) $environment_key;

        if (!ilObject::_exists($user_id, false, 'usr')) {
            throw new ContextException('User does not exist', ContextException::USER_NOT_VALID);
        }
        if (!ilObject::_exists($ref_id, true, 'xlet')) {
            throw new ContextException('Object does not exist', ContextException::ENVIRONMENT_NOT_VALID);
        }
        if (ilObject::_isInTrash($ref_id)) {
            throw new ContextException('Object is deleted', ContextException::ENVIRONMENT_NOT_VALID);
        }

        $this->user = new ilObjUser($user_id);

        // in REST calls the init() function is called from the long-essay-service
        if (ilContext::getType() == ilContext::CONTEXT_REST) {
            if ($this->plugin->getConfig()->getSimulateOffline()) {
                throw new ContextException('Network Problem Simulation', ContextException::SERVICE_UNAVAILABLE);
            }
            \ilLongEssayTaskRestInit::initRestUser($this->user);
        }

        $this->object = new ilObjLongEssayTask($ref_id);

        if (!$this->object->isOnline()) {
            throw new ContextException('Object is offline', ContextException::ENVIRONMENT_NOT_VALID);
        }

       $this->task = $this->localDI->getTaskRepo()->getTaskSettingsById($this->object->getId());
       $this->data = $this->localDI->getDataService($this->object->getId());
    }

    /**
     * @inheritDoc
     */
    public function getSystemName(): string
    {
        global $DIC;
        return (string) $DIC->clientIni()->readVariable('client', 'name');
    }

    /**
     * @inheritDoc
     */
    public function getLanguage(): string
    {
        return $this->user->getLanguage();
    }

    /**
     * @inheritDoc
     */
    public function getTimezone(): string
    {
        return (string) $this->user->getTimeZone();
    }


    /**
     * @inheritDoc
     * here: get the string version of the user id
     */
    public function getUserKey(): string
    {
        return (string) $this->user->getId();
    }

    /**
     * @inheritDoc
     * here: get the string version of the ref_id of the repository object
     */
    public function getEnvironmentKey(): string
    {
        return (string) $this->object->getRefId();
    }


    /**
     * @inheritDoc
     */
    public function getApiToken(string $purpose): ?ApiToken
    {
        $repo = $this->localDI->getEssayRepo();
        $token = $repo->getAccessTokenByUserIdAndTaskId($this->user->getId(), $this->object->getId(), $purpose);
        if (isset($token)) {
            try {
                $expires = (new \ilDateTime($token->getValidUntil(), IL_CAL_DATETIME))->get(IL_CAL_UNIX);
            }
            catch (\ilDateTimeException $e) {
                $expires = 0;
            }
            return new ApiToken($token->getToken(), $token->getIp(), (int) $expires);
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function setApiToken(ApiToken $api_token, string $purpose)
    {
        // delete an existing token
        $repo = $this->localDI->getEssayRepo();
        $repo->deleteAccessTokenByUserIdAndTaskId($this->user->getId(), $this->task->getTaskId(), $purpose);

        // save the new token
        $token = new AccessToken();
        $token->setUserId($this->user->getId());
        $token->setTaskId($this->object->getId());
        $token->setPurpose($purpose);
        if ($api_token->getExpires()) {
            try {
                $valid = (new \ilDateTime($api_token->getExpires(), IL_CAL_UNIX))->get(IL_CAL_DATETIME);
            }
            catch (\ilDateTimeException $e) {
                $valid = null;
            }
        }
        else {
            $valid = null;
        }
        $token->setToken($api_token->getValue());
        $token->setIp($api_token->getIpAddress());
        $token->setValidUntil($valid);
        $repo->createAccessToken($token);
    }


    /**
     * Get the resources that should be available in the app
     * @return EnvResource[]
     */
    public function getResources(): array {
        global $DIC;


        $repo = $this->localDI->getTaskRepo();
        $env_resources = [];

        /** @var Resource $resource */
        foreach ($repo->getResourceByTaskId($this->object->getId()) as $resource) {

            // late static binding - use constant definition in the extended class
            if (in_array($resource->getAvailability(), static::RESOURCES_AVAILABILITIES)) {

                if ($resource->getType() == Resource::RESOURCE_TYPE_FILE) {
                    $resource_file = $DIC->resourceStorage()->manage()->find($resource->getFileId());

                    if($resource_file === null){
                        continue;
                    }

                    $revision = $DIC->resourceStorage()->manage()->getCurrentRevision($resource_file);

                    if($revision === null){
                        continue;
                    }

                    $source = $revision->getInformation()->getTitle();
                    $mimetype = $revision->getInformation()->getMimeType();
                    $size = $revision->getInformation()->getSize();
                }
                else {
                    $mimetype = null;
                    $size = null;
                    $source = $resource->getUrl();
                }

                $env_resources[] = new EnvResource(
                    (string) $resource->getId(),
                    $resource->getTitle(),
                    $resource->getType(),
                    $source,
                    $mimetype,
                    $size
                );
            }
        }

        return $env_resources;
    }


    /**
     * @inheritDoc
     */
    public function sendFileResource(string $key): void
    {
        global $DIC;

        $repo = $this->localDI->getTaskRepo();

        /** @var Resource $resource */
        foreach ($repo->getResourceByTaskId($this->object->getId()) as $resource) {
            if ($resource->getId() == (int) $key && $resource->getType() == Resource::RESOURCE_TYPE_FILE) {
                $identifier = "";

                if (is_string($resource->getFileId())) {
                    $identifier = $resource->getFileId();
                }else {
                    // TODO: ERROR Broken Resource
                }

                $resource_file = $DIC->resourceStorage()->manage()->find($identifier);
                if ($resource_file !== null) {
                    $DIC->resourceStorage()->consume()->inline($resource_file)->run();
                }else{
                    // TODO: Error resource not in Storage
                }
            }
        }
    }


    /**
     * Get the writing task of a certain writer
     * (not needed by interface, but public because needed by CorrectorAdminService)
     */
    public function getWritingTaskByWriterId(int $writer_id) : WritingTask
    {
        $repoWriter = $this->localDI->getWriterRepo()->getWriterById($writer_id);
        $repoEssay = $this->localDI->getEssayRepo()->getEssayByWriterIdAndTaskId($writer_id, $this->task->getTaskId());

        $writing_end = $this->data->dbTimeToUnix($this->task->getWritingEnd());
        if (!empty($writing_end)
            && !empty($timeExtension = $this->localDI->getWriterRepo()->getTimeExtensionByWriterId($writer_id, $this->task->getTaskId()))
        ) {
            $writing_end += $timeExtension->getMinutes() * 60;
        }

       return new WritingTask(
           (string) $this->object->getTitle(),
           (string) $this->task->getInstructions(),
            isset($repoWriter) ? \ilObjUser::_lookupFullname($repoWriter->getUserId()) : '',
            $writing_end,
           $this->data->dbTimeToUnix($repoEssay->getWritingExcluded())
        );
    }

    /**
     * Extend the session of an authenticated user
     *
     * Here:
     * A user may have parallel active sessions
     * We cannot determine the session because the service does not provide its session_id
     * So continue all active sessions, and try to filter them by i if the ip is stored in ilias
     */
    public function setAlive() : void
    {
        global $DIC;

        $client_ini = $DIC->clientIni();
        $system_repo = $this->localDI->getSystemRepo();

        if ($client_ini->readVariable("session", "save_ip")) {
            $session_ids = $system_repo->getActiveSessionIds((int) $this->user->getId(), (string) $_SERVER["REMOTE_ADDR"]);
        }
        else {
            $session_ids = $system_repo->getActiveSessionIds($this->user->getId());
        }

        foreach ($session_ids as $id) {
            $system_repo->setSessionExpires($id, ilSession::getExpireValue());
        }
    }
}