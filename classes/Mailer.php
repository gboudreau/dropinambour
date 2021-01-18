<?php

namespace PommePause\Dropinambour;

use Exception;
use League\Plates\Engine;
use SendGrid;
use SendGrid\Attachment;
use SendGrid\Content;
use SendGrid\Email;
use SendGrid\Mail;
use SendGrid\TrackingSettings;

class Mailer
{
    /**
     * This is required because Heroku doesn't provide mail() functionality.
     *
     * @param string      $email_to     To: email address.
     * @param string      $subject      Subject:
     * @param string|null $text_content Text content.
     * @param string|null $html_content HTML content. Can be null.
     * @param string|null $from_email   From: email address
     * @param string|null $from_name    From: name
     * @param bool        $track_clicks Should we track clicks on links included in this email?
     * @param array|null  $attachments  Files to attach to the email.
     * @param array       $categories   Categories to segment stats in SendGrid.
     *
     * @return bool Did it work?
     * @throws Exception
     */
    public static function send(string $email_to, string $subject, ?string $text_content, ?string $html_content = NULL, ?string $from_email = NULL, ?string $from_name = NULL, bool $track_clicks = FALSE, ?array $attachments = NULL, array $categories = []) : bool {
        if (empty($from_email)) {
            $from_email = Config::get('EMAIL_NOTIF_FROM_ADDRESS');
        }
        if (empty($from_name)) {
            $from_name = Config::get('EMAIL_NOTIF_FROM_NAME');
        }

        Logger::info("Sending email to $email_to");

        $sendgrid = new SendGrid(Config::get('SENDGRID_API_KEY'));

        $_from = new Email($from_name, $from_email);

        if (preg_match('/^(.*) <(.*)>$/', $email_to, $re)) {
            $_to = new Email($re[1], $re[2]);
        } else {
            $_to = new Email(NULL, $email_to);
        }

        $_contents = [];
        if (!empty($text_content)) {
            $_contents[] = new Content("text/plain", $text_content);
        }
        if ($html_content !== NULL) {
            $_contents[] = new Content("text/html", $html_content);
        }
        if (empty($_contents)) {
            throw new Exception("Empty");
        }

        $mail = new Mail($_from, $subject, $_to, $_contents[0]);

        if (count($_contents) == 2) {
            $mail->addContent($_contents[1]);
        }

        if (!empty($categories)) {
            foreach ($categories as $category) {
                $mail->addCategory($category);
            }
        }

        if (!empty($attachments)) {
            foreach ($attachments as $file_name => $file) {
                $file_encoded = base64_encode(file_get_contents($file));
                $attachment = new Attachment();
                $attachment->setContent($file_encoded);
                $attachment->setDisposition("attachment");
                $attachment->setFilename($file_name);
                $mail->addAttachment($attachment);
            }
        }

        // Track opens & clicks?
        $tracking_settings = new TrackingSettings();
        $tracking_settings->setClickTracking($track_clicks);
        $tracking_settings->setOpenTracking($track_clicks);
        $tracking_settings->setSubscriptionTracking(FALSE);

        $response = $sendgrid->client->mail()->send()->post($mail);

        if ($response->statusCode() >= 400) {
            Logger::error("Couldn't send email to $email_to. Response from Sendgrid: " . var_export($response, TRUE));
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Send an email to the specified user, using a specific email template (@see /t/emails)
     *
     * @param string      $email_to     To: email address
     * @param string      $subject      Subject
     * @param string      $template     Email template to use.
     * @param array       $params       Parameters to inject into the template.
     * @param string|null $from_email   From: email address
     * @param string|null $from_name    From: name
     * @param bool        $track_clicks Should we track clicks on links included in this email?
     *
     * @return bool Did it work?
     * @throws Exception
     */
    public static function sendFromTemplate(string $email_to, string $subject, string $template, array $params = [], ?string $from_email = NULL, ?string $from_name = NULL, bool $track_clicks = FALSE) : bool {
        $html_content = static::getEmailHTMLBody($email_to, $template, $params);
        return static::send($email_to, $subject, NULL, $html_content, $from_email, $from_name, $track_clicks, NULL, [$template]);
    }

    /**
     * Generate HTML that can be sent in am email, using a template view, and injectable parameters.
     *
     * @param string $email_to Email address
     * @param string $template Email template; a file in /views/emails
     * @param array  $params   Parameters to be used in the template code
     *
     * @return string HTML
     */
    private static function getEmailHTMLBody(string $email_to, string $template, &$params = []) : string {
        if (is_array($params)) {
            $params = (object) $params;
        }

        if (preg_match('/^(.*) <(.*)>$/', $email_to, $re)) {
            $params->username = $re[1];
            $params->email_address = $re[2];
        } else {
            $params->email_address = $email_to;
        }

        $engine = new Engine('./views/emails/');
        return $engine->render($template, ['params' => $params]);
    }
}
