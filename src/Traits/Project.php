<?php

namespace Jiannius\Autocount\Traits;


trait Project
{
    /**
     * Create project
     * 
     * Payload structure
     * -----------------
     * {
     *   "ProjNo": "jb1",
     *   "Description": "JB1"
     * }
     */
    public function createProject($data)
    {
        $api = $this->callApi(
            uri: 'Project',
            method: 'POST',
            data: $data,
        );

        return $api->json();
    }

    /**
     * Get projects
     */
    public function getProjects($ids = null)
    {
        try {
            $api = $this->callApi(
                uri: 'Project/GetProject',
                method: 'POST',
                data: ['ProjNo' => array_filter((array) $ids)],
            );

            return $api->json();
        }
        catch (\Exception $e) {
            if (str($e->getMessage())->is('*not found*')) return [];
            else throw new \Exception($e->getMessage());
        }
    }

    /**
     * Update project
     */
    public function updateProject($data)
    {
        $api = $this->callApi(
            uri: 'Project/UpdateProject',
            method: 'POST',
            data: $data,
        );

        return data_get($api->json(), 'ResultTable.0');
    }

    /**
     * Delete multiple projects
     */
    public function deleteProjects($ids)
    {
        $api = $this->callApi(
            uri: 'Project/DeleteProject',
            method: 'POST',
            data: [
                'ProjNo' => array_filter((array) $ids),
            ],
        );

        return data_get($api->json(), 'ResultTable');
    }
}