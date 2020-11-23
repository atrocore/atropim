<?php


namespace Pim\Listeners;


use Treo\Core\EventManager\Event;

class Metadata extends \Treo\Listeners\AbstractListener
{
    public function modify(Event $event)
    {
        $data = $event->getArgument('data');

        $data = $this->setProductOwnershipSettings($data);

        $event->setArgument('data', $data);
    }

    protected function setProductOwnershipSettings(array $data): array
    {
        if (isset($data['scopes']['Catalog']['hasAssignedUser']) && $data['scopes']['Catalog']['hasAssignedUser']) {
            $data['entityDefs']['Settings']['fields']['assignedUserProductOwnership']['options'][] = 'fromCatalog';
        }

        if (isset($data['scopes']['Catalog']['hasOwner']) && $data['scopes']['Catalog']['hasOwner']) {
            $data['entityDefs']['Settings']['fields']['ownerUserProductOwnership']['options'][] = 'fromCatalog';
        }

        if (isset($data['scopes']['Catalog']['hasTeam']) && $data['scopes']['Catalog']['hasTeam']) {
            $data['entityDefs']['Settings']['fields']['teamsProductOwnership']['options'][] = 'fromCatalog';
        }

        if (isset($data['scopes']['ProductFamily']['hasAssignedUser']) && $data['scopes']['ProductFamily']['hasAssignedUser']) {
            $data['entityDefs']['Settings']['fields']['assignedUserProductOwnership']['options'][] = 'fromProductFamily';
        }

        if (isset($data['scopes']['ProductFamily']['hasOwner']) && $data['scopes']['ProductFamily']['hasOwner']) {
            $data['entityDefs']['Settings']['fields']['ownerUserProductOwnership']['options'][] = 'fromProductFamily';
        }

        if (isset($data['scopes']['ProductFamily']['hasTeam']) && $data['scopes']['ProductFamily']['hasTeam']) {
            $data['entityDefs']['Settings']['fields']['teamsProductOwnership']['options'][] = 'fromProductFamily';
        }

        return $data;
    }
}