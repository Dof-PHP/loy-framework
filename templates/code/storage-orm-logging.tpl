<?php

declare(strict_types=1);

namespace Domain\__DOMAIN__\Storage__NAMESPACE__;

// use Dof\Framework\Collection;
use Dof\Framework\DDD\ORMStorage;
use Domain\__DOMAIN__\Repository\__NAME__Repository;

/**
 * @Repository(__NAME__Repository)
 * @Driver(mysql)
 * @Database(__DATABASE__)
 * @Table(__TABLE__)
 * @NoSync(0)
 * @_Cache(0)
 * @Comment(Logging table of __NAME__)
 * @Engine(InnoDB)
 * @Index(idx_created_at){at}
 * @Index(idx_action_value){action_value}
 * @Index(idx_operator){operator_id}
 * @Charset(utf8mb4)
 */
class __NAME__ORM extends ORMStorage implements __NAME__Repository
{
    /**
     * @Column(at)
     * @Type(int)
     * @Comment(Created Timestamp)
     * @Length(10)
     * @UnSigned(1)
     */
    protected $at;

    /**
     * @Column(api)
     * @Type(varchar)
     * @Comment(API called by the action: http/cli/...)
     * @Length(16)
     * @Default(unknown)
     */
    protected $api;

    /**
     * @Column(operator_id)
     * @Type(int)
     * @Comment(Operator ID performed the action)
     * @Length(10)
     * @UnSigned(1)
     * @Default(0)
     */
    protected $operatorId;

    /**
     * @Column(operator_ids_forward)
     * @Type(varchar)
     * @Comment(Operator IDs indirectly performed the action, separated with comma)
     * @Length(191)
     * @Default()
     */
    protected $operatorIdsForward;

    /**
     * @Column(title)
     * @Type(varchar)
     * @Comment(Action title)
     * @Length(128)
     * @Default()
     */
    protected $title;

    /**
     * @Column(action_value)
     * @Type(varchar)
     * @Comment(Action value, vary from api type)
     * @Length(191)
     * @Default()
     */
    protected $actionValue;

    /**
     * @Column(action_params)
     * @Type(varchar)
     * @Length(191)
     * @Comment(Action value parameters , vary from api type)
     */
    protected $actionParams;

    /**
    * @Column(action_type)
    * @Type(varchar)
    * @Comment(Action type, vary from api type)
    * @Length(32)
    * @Default()
    */
    protected $actionType;

    /**
    * @Column(arguments)
    * @Type(text)
    * @Comment(Action arguments)
    */
    protected $arguments;

    /**
     * @Column(class)
     * @Type(varchar)
     * @Comment(Class namespace owns server business logic)
     * @Length(191)
     * @Default()
     */
    protected $class;

    /**
     * @Column(method)
     * @Type(varchar)
     * @Comment(Class method owns server business logic)
     * @Length(64)
     * @Default()
     */
    protected $method;

    /**
     * @Column(client_ip)
     * @Type(varchar)
     * @Comment(Client IP performed the action)
     * @Length(64)
     * @Default()
     */
    protected $clientIP;

    /**
     * @Column(client_ips_forward)
     * @Type(varchar)
     * @Comment(Client IP of HTTP agents indirectly performed the action, separated with comma)
     * @Length(191)
     * @Default()
     */
    protected $clientIPsForward;

    /**
     * @Column(client_os)
     * @Type(varchar)
     * @Comment(Client operating system performed the action)
     * @Length(64)
     * @Default()
     */
    protected $clientOS;

    /**
     * @Column(client_name)
     * @Type(varchar)
     * @Comment(Client name performed the action)
     * @Length(64)
     * @Default()
     */
    protected $clientName;

    /**
     * @Column(client_info)
     * @Type(text)
     * @Comment(Client info performed the action)
     * @NotNull(0)
     */
    protected $clientInfo;

    /**
     * @Column(client_port)
     * @Type(smallint)
     * @Comment(Client port performed the action)
     * @Length(6)
     * @UnSigned(1)
     * @Default(0)
     */
    protected $clientPort;

    /**
     * @Column(server_ip)
     * @Type(varchar)
     * @Comment(Server IP response the action)
     * @Length(64)
     * @Default()
     */
    protected $serverIP;

    /**
     * @Column(server_os)
     * @Type(varchar)
     * @Comment(Server operating system response the action)
     * @Length(64)
     * @Default()
     */
    protected $serverOS;

    /**
     * @Column(server_name)
     * @Type(varchar)
     * @Comment(Server name response the action)
     * @Length(64)
     * @Default()
     */
    protected $serverName;

    /**
     * @Column(server_info)
     * @Type(text)
     * @Comment(Server info response the action)
     * @NotNull(0)
     */
    protected $serverInfo;

    /**
     * @Column(server_port)
     * @Type(smallint)
     * @Comment(Server port response the action)
     * @Length(6)
     * @UnSigned(1)
     * @Default(0)
     */
    protected $serverPort;

    /**
     * @Column(server_status)
     * @Type(int)
     * @Comment(Server status code after responsing the action)
     * @Length(1)
     * @UnSigned(1)
     * @Default(0)
     */
    protected $serverStatus;

    /**
     * @Column(server_error)
     * @Type(tinyint)
     * @Comment(Server business logic occur errors or not during responsing the action)
     * @UnSigned(1)
     * @Length(1)
     * @Default(0)
     */
    protected $serverError;

    public function logging(array $context)
    {
        $this->builder()->add($context);
    }
}
