<?php

namespace ILIAS\Plugin\LongEssayTask\Task;

use ILIAS\FileUpload\DTO\UploadResult;
use ILIAS\Plugin\LongEssayTask\Data\Resource;
use ILIAS\Plugin\LongEssayTask\LongEssayTaskDI;
use ILIAS\ResourceStorage\Identification\ResourceIdentification;

class ResourceAdmin
{
    /**
     * @var int
     */
    protected $task_id;

    /**
     * @param int $a_task_id
     */
    public function __construct(int $a_task_id)
    {
        $this->task_id = $a_task_id;
    }

    /**
     * @param string $a_title
     * @param string $a_description
     * @param string $a_availability
     * @param UploadResult $a_upload
     * @param int $a_user_id
     * @return int
     */
    public function saveFileResource(string $a_title, string $a_description, string $a_availability, UploadResult $a_upload, int $a_user_id = 6): int
    {
        global $DIC;
        $stakeholder = new ResourceResourceStakeholder($a_user_id);
        $identification = (string) $DIC->resourceStorage()->manage()->upload($a_upload, $stakeholder);

        $resource = new Resource();
        $resource->setType(Resource::RESOURCE_TYPE_FILE);
        $resource->setTitle($a_title);
        $resource->setDescription($a_description);
        $resource->setAvailability($this->validateAvailability($a_availability));
        $resource->setTaskId($this->getTaskId());
        $resource->setFileId($identification);

        $let_dic = LongEssayTaskDI::getInstance();
        $task_repo = $let_dic->getTaskRepo();
        $task_repo->createResource($resource);

        return $resource->getId();
    }

    /**
     * @param string $a_title
     * @param string $a_description
     * @param string $a_availability
     * @param string $a_url
     * @return int
     */
    public function saveURLResource(string $a_title, string $a_description, string $a_availability, string $a_url): int
    {
        $resource = new Resource();
        $resource->setType(Resource::RESOURCE_TYPE_FILE);
        $resource->setTitle($a_title);
        $resource->setDescription($a_description);
        $resource->setAvailability($this->validateAvailability($a_availability));
        $resource->setTaskId($this->getTaskId());
        $resource->setUrl($a_url);

        $let_dic = LongEssayTaskDI::getInstance();
        $task_repo = $let_dic->getTaskRepo();
        $task_repo->createResource($resource);

        return $resource->getId();
    }

    /**
     * @param int $a_id
     * @param string $a_title
     * @param string $a_description
     * @param string $a_availability
     * @param string $a_url
     * @return bool
     */
    public function updateResource(int $a_id, string $a_title, string $a_description, string $a_availability, string $a_url=""): bool{
        $let_dic = LongEssayTaskDI::getInstance();
        $task_repo = $let_dic->getTaskRepo();
        $resource = $task_repo->getResourceById($a_id);

        if($resource != null){
            $resource->setTitle($a_title);
            $resource->setDescription($a_description);
            $resource->setAvailability($this->validateAvailability($a_availability));
            if($resource->getType() == Resource::RESOURCE_TYPE_URL)
            {
                $resource->setUrl($a_url);
            }
            $task_repo->updateResource($resource);
            return true;
        }

        return false;
    }

    /**
     * @param int $a_id
     * @param int $a_user_id
     * @param UploadResult $a_upload
     * @return bool
     */
    public function updateResourceFile(int $a_id, int $a_user_id, UploadResult $a_upload): bool {
        global $DIC;
        $let_dic = LongEssayTaskDI::getInstance();
        $task_repo = $let_dic->getTaskRepo();
        $resource = $task_repo->getResourceById($a_id);

        if($resource != null && $resource->getType() == Resource::RESOURCE_TYPE_FILE) {
            $stakeholder = new ResourceResourceStakeholder($a_user_id);
            $identification = new ResourceIdentification($resource->getFileId());

            $DIC->resourceStorage()->manage()->replaceWithUpload($identification, $a_upload, $stakeholder);
            return true;
        }

        return false;
    }

    /**
     * @param int $a_id
     * @return array
     */
    public function getResource(int $a_id = 0): array
    {
        $let_dic = LongEssayTaskDI::getInstance();
        $task_repo = $let_dic->getTaskRepo();
        $resource = $task_repo->getResourceById($a_id);

        if($resource == null)
        {
            $resource = new Resource();
        }

        return [
          "id" => $resource->getId(),
            "title" => $resource->getTitle(),
            "description" => $resource->getDescription(),
            "type" => $resource->getType(),
            "availability" => $resource->getAvailability(),
            "url" => $resource->getUrl(),
            "file" => $resource->getType() == Resource::RESOURCE_TYPE_FILE ?
                new ResourceIdentification($resource->getFileId()): null
        ];
    }


    /**
     * @return int
     */
    public function getTaskId(): int
    {
        return $this->task_id;
    }

    /**
     * @param int $task_id
     * @return ResourceAdmin
     */
    public function setTaskId(int $task_id): ResourceAdmin
    {
        $this->task_id = $task_id;
        return $this;
    }

    /**
     *
     * @param string $a_availability
     * @return string
     */
    protected function validateAvailability(string $a_availability): string{
        if(in_array($a_availability,
            [Resource::RESOURCE_AVAILABILITY_AFTER,
                Resource::RESOURCE_AVAILABILITY_DURING,
                Resource::RESOURCE_AVAILABILITY_BEFORE])
        ){
            return $a_availability;
        }
        return Resource::RESOURCE_AVAILABILITY_AFTER;
    }
}