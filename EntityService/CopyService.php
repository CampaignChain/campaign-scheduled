<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Campaign\ScheduledCampaignBundle\EntityService;

use CampaignChain\CoreBundle\Entity\Campaign;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use CampaignChain\CoreBundle\Entity\Action;
use CampaignChain\CoreBundle\Entity\Module;

class CopyService
{
    const BUNDLE_NAME = 'campaignchain/campaign-scheduled';
    const MODULE_IDENTIFIER = 'campaignchain-scheduled';

    protected $em;
    protected $container;
    protected $logger;

    public function __construct(EntityManager $em, ContainerInterface $container)
    {
        $this->em = $em;
        $this->container = $container;
        $this->logger = $this->container->get('logger');
    }

    public function scheduled2Scheduled(Campaign $scheduledCampaign, \DateTime $startDate, $status = null, $name = null)
    {
        $campaignService = $this->container->get('campaignchain.core.campaign');

        try {
            $this->em->getConnection()->beginTransaction();

            // Clone the campaign template.
            $copiedScheduledCampaign = $campaignService->cloneCampaign(
                $scheduledCampaign
            );

            if($name != null) {
                $copiedScheduledCampaign->setName($name);
            }

            $this->em->flush();

            // Move the cloned campaign to the start date.
            $copiedScheduledCampaign = $campaignService->moveCampaign(
                $copiedScheduledCampaign, $startDate,
                Action::STATUS_OPEN
            );

            $this->em->getConnection()->commit();

            return $copiedScheduledCampaign;

        } catch (\Exception $e) {
            $this->em->getConnection()->rollback();
            throw $e;
        }
    }

    public function template2Scheduled(Campaign $campaignTemplate, \DateTime $startDate, $status = null, $name = null)
    {
        $campaignService = $this->container->get('campaignchain.core.campaign');

        try {
            $this->em->getConnection()->beginTransaction();

            // Clone the campaign template.
            $scheduledCampaign = $campaignService->cloneCampaign(
                $campaignTemplate
            );

            // Change module relationship of cloned campaign
            $moduleService = $this->container->get('campaignchain.core.module');
            $scheduledCampaign->setCampaignModule(
                $moduleService->getModule(
                    Module::REPOSITORY_CAMPAIGN,
                    self::BUNDLE_NAME,
                    self::MODULE_IDENTIFIER
                )
            );
            // Specify other parameters of copied campaign.
            if($name != null) {
                $scheduledCampaign->setName($name);
            }
            $scheduledCampaign->setHasRelativeDates(false);
            $scheduledCampaign->setStatus(Action::STATUS_OPEN);
            $hookService = $this->container->get('campaignchain.core.hook');
            $scheduledCampaign->setTriggerHook(
                $hookService->getHook('campaignchain-duration')
            );

            $this->em->flush();

            // Move the cloned campaign to the start date.
            $scheduledCampaign = $campaignService->moveCampaign(
                $scheduledCampaign, $startDate,
                Action::STATUS_OPEN
            );

            $this->em->getConnection()->commit();

            return $scheduledCampaign;
        } catch (\Exception $e) {
            $this->em->getConnection()->rollback();
            throw $e;
        }
    }

    public function repeating2Scheduled(Campaign $campaignTemplate, \DateTime $startDate, $status = null, $name = null)
    {

    }
}