<?php

namespace NW\WebService\References\Operations\Notification;

/*
 * Назначение класса - рассылка уведомлений клиентам и сотрудникам при наступлении определенных событий
 */
class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW    = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');
        $resellerId = (int)$data['resellerId'];
        $clientId = (int)$data['clientId'];
        $notificationType = (int)$data['notificationType'];
        $creatorId = (int)$data['creatorId'];
        $expertId = (int)$data['expertId'];

        //при необходимости для нижеследующих полей можно добавить проверку на пустоту, например
        $complaintId = (int)$data['complaintId'];
        $complaintNumber = (string)$data['complaintNumber'];
        $consumptionId = (int)$data['consumptionId'];
        $consumptionNumber = (string)$data['consumptionNumber'];
        $agreementNumber = (string)$data['agreementNumber'];
        $date = (string)$data['date'];

        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail'   => false,
            'notificationClientBySms'     => [
                'isSent'  => false,
                'message' => '',
            ],
        ];

        if (empty($resellerId)) {
            throw new \Exception('Empty resellerId', 400);
        }

        if (empty($notificationType)) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById($resellerId);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 400);
        }

        $client = Contractor::getById($clientId);
        if ($client === null || $client->getType() !== Contractor::TYPE_CUSTOMER || $client->Seller->getId() !== $resellerId) {
            throw new \Exception('Client not found!', 400);
        }

        $cFullName = $client->getFullName();
        if (empty($cFullName)) {
            throw new \Exception('No client name!', 400);
        }

        $cr = Employee::getById($creatorId);
        if ($cr === null) {
            throw new \Exception('Creator not found!', 400);
        }

        $et = Employee::getById($expertId);
        if ($et === null) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                    'FROM' => Status::getName((int)$data['differences']['from']),
                    'TO'   => Status::getName((int)$data['differences']['to']),
                ], $resellerId);
        }

        $templateData = [
            'COMPLAINT_ID'       => $complaintId,
            'COMPLAINT_NUMBER'   => $complaintNumber,
            'CREATOR_ID'         => $creatorId,
            'CREATOR_NAME'       => $cr->getFullName(),
            'EXPERT_ID'          => $expertId,
            'EXPERT_NAME'        => $et->getFullName(),
            'CLIENT_ID'          => $clientId,
            'CLIENT_NAME'        => $cFullName,
            'CONSUMPTION_ID'     => $consumptionId,
            'CONSUMPTION_NUMBER' => $consumptionNumber,
            'AGREEMENT_NUMBER'   => $agreementNumber,
            'DATE'               => $date,
            'DIFFERENCES'        => $differences,
        ];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom();
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $email,
                           /* Имеются вопросы по получению темы и тела письма.
                            * Ошибочно используется функция перевода __(). По всей вероятности в templateData должны были быть заданы ключи тема и тела письма с их содержимым
                            * и передаваться в поля subject и message письма
                            */
                           'subject'   => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;

            }
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->getEmail())) {
                MessagesClient::sendMessage([
                    0 => [ // MessageTypes::EMAIL
                           'emailFrom' => $emailFrom,
                           'emailTo'   => $client->getEmail(),
                            /* Имеются вопросы по получению темы и тела письма.
                             * Ошибочно используется функция перевода __(). По всей вероятности в templateData должны были быть заданы ключи тема и тела письма с их содержимым
                             * и передаваться в поля subject и message письма
                             */
                           'subject'   => __('complaintClientEmailSubject', $templateData, $resellerId),
                           'message'   => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, $client->getId(), NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send($resellerId, $client->getId(), NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                } else if(!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
}
