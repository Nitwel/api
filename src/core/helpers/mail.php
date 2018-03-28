<?php

use Directus\Mail\Mail;
use Directus\Util\ArrayUtils;

if (!function_exists('send_email')) {
    /**
     * Sends a new email
     *
     * @param string $viewPath
     * @param array $data
     * @param callable $callback
     */
    function send_email($viewPath, array $data, callable $callback)
    {
        $app = \Directus\Application\Application::getInstance();
        /** @var \Directus\Mail\MailerManager $mailerManager */
        $mailerManager = $app->getContainer()->get('mailer_manager');
        $config = $app->getConfig();

        $mailer = $mailerManager->getDefault();
        $mailerKey = $mailer->getName();
        $message = $mailer->createMessage();

        // default mail from address
        $mailConfig = $config->get('mail.' . $mailerKey);
        $message->setFrom(ArrayUtils::get($mailConfig, 'from'));

        $bcc = ArrayUtils::get($mailConfig, 'bcc', null);
        if ($bcc !== null) {
            $message->setBcc($bcc);
        }

        $cc =  ArrayUtils::get($mailConfig, 'cc', null);
        if ($cc !== null) {
            $message->setCc($cc);
        }

        call_user_func($callback, $message);

        if ($message->getBody() === null) {
            $content = parse_twig($viewPath, array_merge(
                $data,
                ['api' => ['env' => get_api_env()]]
            ));
            $message->setBody($content, 'text/html');
        }

        $mailer->send($message);
    }
}

if (!function_exists('parse_twig')) {
    /**
     * Parse twig view
     *
     * @param string $viewPath
     * @param array $data
     *
     * @return string
     */
    function parse_twig($viewPath, array $data)
    {
        $app = \Directus\Application\Application::getInstance();

        $mailSettings = [];
        $settings = $app->getContainer()->get('app_settings');
        foreach ($settings as $setting) {
            $mailSettings[$setting['scope']][$setting['key']] = $setting['value'];
        }

        $data = array_merge(['settings' => $mailSettings], $data);

        return $app->getContainer()->get('mail_view')->fetch($viewPath, $data);
    }
}

if (!function_exists('send_reset_password_email')) {
    /**
     * Sends a new password email
     *
     * @param $user
     * @param string $password
     */
    function send_reset_password_email($user, $password)
    {
        $data = ['new_password' => $password];
        send_email('reset-password.twig', $data, function (Swift_Message $message) use ($user) {
            $message->setSubject(__t('password_reset_new_password_email_subject'));
            $message->setTo($user['email']);
        });
    }
}

if (!function_exists('send_forgot_password_email')) {
    /**
     * Sends a new reset password email
     *
     * @param $user
     * @param string $token
     */
    function send_forgot_password_email($user, $token)
    {
        $data = ['reset_token' => $token];
        send_email('forgot-password.twig', $data, function (Swift_Message $message) use ($user) {
            $message->setSubject(__t('password_forgot_password_reset_email_subject'));
            $message->setTo($user['email']);
        });
    }
}

if (!function_exists('send_message_notification_email')) {
    /**
     * Sends a new message notification
     *
     * @param $user
     * @param array $payload
     *   [from] => 1
     *   [responses] => Array()
     *   [subject] => RE: Subject
     *   [recipients] => 0_1,0_2,0_1
     *   [response_to] => 3
     *   [message] => Lorem ipsum!
     *   [attachment] =>
     *   [id] => 15
     */
    function send_message_notification_email($user, array $payload)
    {
        $adapter = \Directus\Bootstrap::get('zendDb');
        $acl = \Directus\Bootstrap::get('acl');
        $table = new \Directus\Database\TableGateway\DirectusUsersTableGateway($adapter, $acl);
        $sender = $table->find($payload['from']);
        $data = [
            'message' => $payload['message'],
            'sender' => $sender,
            'message_id' => ArrayUtils::get($payload, 'response_to', $payload['id'])
        ];
        send_email('notification.twig', $data, function (Swift_Message $message) use ($user, $payload) {
            $message->setSubject($payload['subject']);
            $message->setTo($user['email']);
        });
    }
}


if (!function_exists('send_new_install_email')) {
    /**
     * Sends a new installation email
     *
     * @param array $data
     */
    function send_new_install_email(array $data)
    {
        send_email('new-install.twig', $data, function (Swift_Message $message) use ($data) {
            $message->setSubject(__t('email_subject_your_new_directus_instance_x', [
                'name' => $data['project']['name']
            ]));
            $message->setTo($data['user']['email']);
        });
    }
}

if (!function_exists('send_user_invitation_email')) {
    /**
     * Sends a invitation email
     *
     * @param string $email
     * @param string $token
     */
    function send_user_invitation_email($email, $token)
    {
        $data = ['token' => $token];
        send_email('user-invitation.twig', $data, function (\Directus\Mail\MessageInterface $message) use ($email) {
            // TODO: Add a proper invitation subject
            $message->setSubject('Invitation to Directus');
            $message->setTo($email);
        });
    }
}
