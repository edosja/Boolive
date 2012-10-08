<?php
/**
 * Обратная связь
 * Информационный объект обратной связи. Содержит параметры отправления и сообщение.
 * @version 1.0
 */
namespace Library\content_samples\Feedback;

use Boolive\data\Entity,
    Boolive\mail\Mail,
    Boolive\values\Check,
    Boolive\values\Rule;

class Feedback extends Entity
{
    public function send()
    {
        if ($this->check()){
            try{
                return Mail::send(
                    $this->email_to->getValue(),
                    Check::filter($this->title->getValue(), Rule::escape()),
                    Check::filter($this->message->getValue(), Rule::escape()),
                    $this->email_from->getValue()
                );
            }catch (\Exception $e){
                return false;
            }
        }
        return false;
    }
}