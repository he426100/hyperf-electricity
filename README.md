# Hyperf电表采集器网关

基于Hyperf框架开发的电表采集器网关系统，用于连接和管理电表采集器，实现对电表数据的远程采集与控制。系统通过TCP服务器与采集器建立连接，并提供HTTP API接口供前端或其他系统调用。


## 使用说明


### HTTP API接口

#### 发送设备指令

- 请求方式：POST
- 请求地址：/
- 请求参数：
  ```json
  {
    "timeOut": 5,
    "gatewayId": "g1",
    "machineId": "01",
    "data": "FF FF FF"
  }
  ```
- 返回示例：
  ```json
  {
    "status": 1,
    "data": "01 FF FF FF"
  }
  ```

### TCP通信协议

#### 采集器登录

- 请求格式：
  ```json
  {
    "type": "login",
    "name": "FF"
  }
  ```
- 响应格式：
  ```json
  {
    "type": "login",
    "data": 1
  }
  ```

#### 心跳包

- 请求格式：
  ```json
  {
    "type": "ping",
    "data": 1
  }
  ```
- 响应格式：
  ```json
  {
    "type": "ping",
    "data": 1
  }
  ```

## 设置

1. 串口设置：透明传输
2. 通信协议：Modbus TCP协议
