<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Cache
 * @subpackage Storage
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Cache\Storage\Plugin;

use stdClass,
    Traversable,
    Zend\Cache\Exception,
    Zend\Cache\Storage\Capabilities,
    Zend\Cache\Storage\Event,
    Zend\Cache\Storage\PostEvent,
    Zend\EventManager\EventManagerInterface;

/**
 * @category   Zend
 * @package    Zend_Cache
 * @subpackage Storage
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Serializer extends AbstractPlugin
{
    /**
     * @var array
     */
    protected $capabilities = array();

    /**
     * Handles
     *
     * @var array
     */
    protected $handles = array();

    /**
     * Attach
     *
     * @param  EventManagerInterface $events
     * @param  int                   $priority
     * @return Serializer
     * @throws Exception\LogicException
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $index = spl_object_hash($events);
        if (isset($this->handles[$index])) {
            throw new Exception\LogicException('Plugin already attached');
        }

        $handles = array();
        $this->handles[$index] = & $handles;

        // The higher the priority the sooner the plugin will be called on pre events
        // but the later it will be called on post events.
        $prePriority  = $priority;
        $postPriority = -$priority;

        // read
        $handles[] = $events->attach('getItem.post',  array($this, 'onReadItemPost'), $postPriority);
        $handles[] = $events->attach('getItems.post', array($this, 'onReadItemsPost'), $postPriority);

        // fetch / fetchAll
        $handles[] = $events->attach('fetch.post', array($this, 'onFetchPost'), $postPriority);
        $handles[] = $events->attach('fetchAll.post', array($this, 'onFetchAllPost'), $postPriority);

        // write
        $handles[] = $events->attach('setItem.pre',  array($this, 'onWriteItemPre'), $prePriority);
        $handles[] = $events->attach('setItems.pre', array($this, 'onWriteItemsPre'), $prePriority);

        $handles[] = $events->attach('addItem.pre',  array($this, 'onWriteItemPre'), $prePriority);
        $handles[] = $events->attach('addItems.pre', array($this, 'onWriteItemsPre'), $prePriority);

        $handles[] = $events->attach('replaceItem.pre',  array($this, 'onWriteItemPre'), $prePriority);
        $handles[] = $events->attach('replaceItems.pre', array($this, 'onWriteItemsPre'), $prePriority);

        $handles[] = $events->attach('checkAndSetItem.pre', array($this, 'onWriteItemPre'), $prePriority);

        // increment / decrement item(s)
        $handles[] = $events->attach('incrementItem.pre', array($this, 'onIncrementItemPre'), $prePriority);
        $handles[] = $events->attach('incrementItems.pre', array($this, 'onIncrementItemsPre'), $prePriority);

        $handles[] = $events->attach('decrementItem.pre', array($this, 'onDecrementItemPre'), $prePriority);
        $handles[] = $events->attach('decrementItems.pre', array($this, 'onDecrementItemsPre'), $prePriority);

        // overwrite capabilities
        $handles[] = $events->attach('getCapabilities.post',  array($this, 'onGetCapabilitiesPost'), $postPriority);

        return $this;
    }

    /**
     * Detach
     *
     * @param  EventManagerInterface $events
     * @return Serializer
     * @throws Exception\LogicException
     */
    public function detach(EventManagerInterface $events)
    {
        $index = spl_object_hash($events);
        if (!isset($this->handles[$index])) {
            throw new Exception\LogicException('Plugin not attached');
        }

        // detach all handles of this index
        foreach ($this->handles[$index] as $handle) {
            $events->detach($handle);
        }

        // remove all detached handles
        unset($this->handles[$index]);

        return $this;
    }

    /**
     * On read item post
     *
     * @param  PostEvent $event
     * @return void
     */
    public function onReadItemPost(PostEvent $event)
    {
        $options    = $this->getOptions();
        $serializer = $options->getSerializer();
        $result     = $event->getResult();
        $result     = $serializer->unserialize($result);
        $event->setResult($result);
    }

    /**
     * On read items post
     *
     * @param  PostEvent $event
     * @return void
     */
    public function onReadItemsPost(PostEvent $event)
    {
        $options    = $this->getOptions();
        $serializer = $options->getSerializer();
        $result     = $event->getResult();
        foreach ($result as &$value) {
            $value = $serializer->unserialize($value);
        }
        $event->setResult($result);
    }

    /**
     * On fetch post
     *
     * @param  PostEvent $event
     * @return void
     */
    public function onFetchPost(PostEvent $event)
    {
        $options    = $this->getOptions();
        $serializer = $options->getSerializer();
        $item       = $event->getResult();
        if (isset($item['value'])) {
            $item['value'] = $serializer->unserialize($item['value']);
        }
        $event->setResult($item);
    }

    /**
     * On fetch all post
     *
     * @param  PostEvent $event
     * @return void
     */
    public function onFetchAllPost(PostEvent $event)
    {
        $options    = $this->getOptions();
        $serializer = $options->getSerializer();
        $result     = $event->getResult();
        foreach ($result as &$item) {
            if (isset($item['value'])) {
                $item['value'] = $serializer->unserialize($item['value']);
            }
        }
        $event->setResult($result);
    }

    /**
     * On write item pre
     *
     * @param  Event $event
     * @return void
     */
    public function onWriteItemPre(Event $event)
    {
        $options    = $this->getOptions();
        $serializer = $options->getSerializer();
        $params     = $event->getParams();
        $params['value'] = $serializer->serialize($params['value']);
    }

    /**
     * On write items pre
     *
     * @param  Event $event
     * @return void
     */
    public function onWriteItemsPre(Event $event)
    {
        $options    = $this->getOptions();
        $serializer = $options->getSerializer();
        $params     = $event->getParams();
        foreach ($params['keyValuePairs'] as &$value) {
            $value = $serializer->serialize($value);
        }
    }

    /**
     * On increment item pre
     *
     * @param  Event $event
     * @return mixed
     */
    public function onIncrementItemPre(Event $event)
    {
        $event->stopPropagation(true);

        $cache    = $event->getTarget();
        $params   = $event->getParams();
        $token    = null;
        $oldValue = $cache->getItem(
            $params['key'],
            array('token' => &$token) + $params['options']
        );
        return $cache->checkAndSetItem(
            $token,
            $oldValue + $params['value'],
            $params['key'],
            $params['options']
        );
    }

    /**
     * On increment items pre
     *
     * @param  Event $event
     * @return mixed
     */
    public function onIncrementItemsPre(Event $event)
    {
        $event->stopPropagation(true);

        $cache  = $event->getTarget();
        $params = $event->getParams();
        $keyValuePairs = $cache->getItems(array_keys($params['keyValuePairs']), $params['options']);
        foreach ($params['keyValuePairs'] as $key => &$value) {
            if (isset($keyValuePairs[$key])) {
                $keyValuePairs[$key]+= $value;
            } else {
                $keyValuePairs[$key] = $value;
            }
        }
        return $cache->setItems($keyValuePairs, $params['options']);
    }

    /**
     * On decrement item pre
     *
     * @param  Event $event
     * @return mixed
     */
    public function onDecrementItemPre(Event $event)
    {
        $event->stopPropagation(true);

        $cache    = $event->getTarget();
        $params   = $event->getParams();
        $token    = null;
        $oldValue = $cache->getItem(
            $params['key'],
            array('token' => &$token) + $params['options']
        );
        return $cache->checkAndSetItem(
            $token,
            $oldValue - $params['value'],
            $params['key'],
            $params['options']
        );
    }

    /**
     * On decrement items pre
     *
     * @param  Event $event
     * @return mixed
     */
    public function onDecrementItemsPre(Event $event)
    {
        $event->stopPropagation(true);

        $cache         = $event->getTarget();
        $params        = $event->getParams();
        $keyValuePairs = $cache->getItems(array_keys($params['keyValuePairs']), $params['options']);
        foreach ($params['keyValuePairs'] as $key => &$value) {
            if (isset($keyValuePairs[$key])) {
                $keyValuePairs[$key]-= $value;
            } else {
                $keyValuePairs[$key] = -$value;
            }
        }
        return $cache->setItems($keyValuePairs, $params['options']);
    }

    /**
     * On get capabilities
     *
     * @param  PostEvent $event
     * @return void
     */
    public function onGetCapabilitiesPost(PostEvent $event)
    {
        $baseCapabilities = $event->getResult();
        $index = spl_object_hash($baseCapabilities);

        if (!isset($this->capabilities[$index])) {
            $this->capabilities[$index] = new Capabilities(
                $baseCapabilities->getAdapter(),
                new stdClass(), // marker
                array('supportedDatatypes' => array(
                    'NULL'     => true,
                    'boolean'  => true,
                    'integer'  => true,
                    'double'   => true,
                    'string'   => true,
                    'array'    => true,
                    'object'   => 'object',
                    'resource' => false,
                )),
                $baseCapabilities
            );
        }

        $event->setResult($this->capabilities[$index]);
    }
}
