<?php

namespace NW\WebService\References\Operations\Notification;

/**
 * @property Seller $Seller
 */
/*
 * Абстрактный класс, описывающий сущность контрактора.
 * Ради соблюдения принципа инкапсуляции, сделал поля класса приватными, плюс добавил фамилию
 */

class Contractor
{
    const TYPE_CUSTOMER = 0;
    private $id;
    private $type;
    private $firstName;
    private $lastName;

    //добавил поле email
    private $email;

    /*Немного странный метод, поскольку создает и возвращает пустой объект,
     * а не получает вначале данные по id из БД, к примеру, и возвращает созданный на основе этих данных объект
     */
    public static function getById(int $resellerId): self
    {
        return new self($resellerId); // fakes the getById method
    }

    //id к имени не относится, поэтому возвращаю имя и фамилию человека
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    //добавил геттер для поля id
    public function getId() : int
    {
        return $this->id;
    }

    //добавил геттер для поля type
    public function getType() : string
    {
        return $this->type;
    }

    //добавил геттер для поля email
    public function getEmail() : string
    {
        return $this->email;
    }

}

/*
 * Сущность продавца.
 * Не вижу конструктора, в который бы передавался id, имя, фамилия и email человека
 * (констуктор можно было определить в абстрактном классе)
 */

class Seller extends Contractor
{
}

/*
 * Сущность работника.
 * Не вижу конструктора, в который бы передавался id, имя, фамилия и email человека
 * (констуктор можно было определить в абстрактном классе)
 */

class Employee extends Contractor
{
}


/*
 * Назначение класса - работа со словарем статусов
 * Переименовал класс, чтобы его название соответствовало задаче, который он выполняет
 */

class StatusWork
{
    private const statuses = [
        0 => 'Completed',
        1 => 'Pending',
        2 => 'Rejected',
    ];

    /* Назначение метода - возвращение названия статуса по ключу
     * Переименовал переменную $a в statuses и вынес ее в константу класса, обработал случай отсутствия ключа
     * Ключи стоило было задать в текстовом виде для удобства, например:
     * private const statuses = [
                'COMPLETED' => 'Completed',
                'PENDING' => 'Pending',
                'REJECTED' => 'Rejected',
            ];
    */
    public static function getName(int $id): string
    {
        if (isset(self::statuses[$id])) {
            return self::statuses[$id];
        } else {
            throw new \Exception('Unknown status');
        }
    }

    //добавил метод возвращения всего словаря статусов
    public static function getStatues(): array
    {
        return self::statuses;
    }

}

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    //переименовал метод
    public function getRequestDataByKey($pName)
    {
        return $_REQUEST[$pName];
    }
}


/*Два нижеследующих класса следовало бы объединить в один,
* который хранил бы в себе почтовые адреса в виде массива и возвращал бы их по разным ключам
*/
function getResellerEmailFrom()
{
    return 'contractor@example.com';
}

function getEmailsByPermit()
{
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com'];
}

/*
 * Класс, хранящий в себе константы статусов
 */
class NotificationEvents
{
    const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    const NEW_RETURN_STATUS = 'newReturnStatus';
}