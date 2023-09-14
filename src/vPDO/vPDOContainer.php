<?php
/**
 * Этот файл является частью пакета vPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace vPDO;


use ArrayAccess;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use vPDO\Exception\Container\ContainerException;
use vPDO\Exception\Container\NotFoundException;

/**
 * Реализует минимальный контейнер для размещения сервиса.
 *
 * @package vPDO
 */
class vPDOContainer implements ContainerInterface, ArrayAccess
{
    private $entries = array();

    /**
     * Добавьте запись в контейнер с указанным идентификатором.
     *
     * @param string $id Идентификатор для записи.
     * @param mixed  $entry Запись для добавления.
     */
    public function add(string $id, $entry)
    {
        $this->offsetSet($id, $entry);
    }

    /**
     * Находит запись контейнера по его идентификатору и возвращает ее.
     *
     * @param string $id Идентификатор записи, которую нужно искать.
     *
     * @throws NotFoundExceptionInterface  Для **this** идентификатора не было найдено ни одной записи.
     * @throws ContainerExceptionInterface Ошибка при извлечении записи.
     *
     * @return mixed Entry.
     */
    public function get(string $id)
    {
        if ($this->has($id)) {
            try {
                return $this->offsetGet($id);
            } catch (Exception $e) {
                throw new ContainerException($e->getMessage(), $e->getCode(), $e);
            }
        }
        throw new NotFoundException("Зависимость не найдена с ключом {$id}.");
    }

    /**
     * Возвращает значение true, если контейнер может вернуть запись для данного идентификатора.
     * В противном случае возвращает значение false.
     *
     * @param string $id Идентификатор записи, которую нужно искать.
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->offsetExists($id);
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->entries);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->entries[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->entries[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->entries[$offset]);
    }
}
