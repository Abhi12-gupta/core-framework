<?php

namespace Webkul\UVDesk\CoreFrameworkBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Webkul\UVDesk\CoreFrameworkBundle\SwiftMailer\Event\ConfigurationRemovedEvent;
use Symfony\Component\Translation\TranslatorInterface;
use Webkul\UVDesk\CoreFrameworkBundle\SwiftMailer\SwiftMailer as SwiftMailerService;

class SwiftMailerXHR extends Controller
{
    private $translator;
    private $swiftMailer;
    
    public function __construct(TranslatorInterface $translator,SwiftMailerService $swiftMailer)
    {
        $this->translator = $translator;
        $this->swiftMailer = $swiftMailer;
    }

    public function loadMailersXHR(Request $request)
    {
        if (true === $request->isXmlHttpRequest()) {
            $collection = array_map(function ($configuartion) {
                return [
                    'id' => $configuartion->getId(),
                    'email' => $configuartion->getUsername(),
                    'transport' => $configuartion->getTransportName(),
                    'isActive' => $configuartion->getDeliveryStatus(),
                ];
            }, $this->swiftMailer->parseSwiftMailerConfigurations());

            return new JsonResponse($collection);
        } 

        return new JsonResponse([], 404);
    }

    public function removeMailerConfiguration(Request $request)
    {
        $params = $request->query->all();
        $swiftmailer = $this->swiftMailer;
        $configurations = $swiftmailer->parseSwiftMailerConfigurations();
       
        if (!empty($configurations)) {
            foreach ($configurations as $index => $configuration) {
                if ($configuration->getId() == $params['id']) {
                    $swiftmailerConfiguration = $configuration;
                    break;
                }
            }

            if (!empty($swiftmailerConfiguration)) {
                unset($configurations[$index]);

                // Dispatch swiftmailer configuration removed event
                $event = new ConfigurationRemovedEvent($swiftmailerConfiguration);
                $this->get('uvdesk.core.event_dispatcher')->dispatch(ConfigurationRemovedEvent::NAME, $event);

                // Update swiftmailer configuration file
                $swiftmailer->writeSwiftMailerConfigurations($configurations);
                
                return new JsonResponse([
                    'alertClass' => 'success',
                    'alertMessage' => $this->translator->trans('Swiftmailer configuration removed successfully.'),
                ]);
            }
        }

        return new JsonResponse([
            'alertClass' => 'error',
            'alertMessage' => $this->translator->trans('No swiftmailer configurations found for mailer id:') . $params['id'],
        ], 404);
    }
}
