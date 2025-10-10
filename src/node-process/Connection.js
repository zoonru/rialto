'use strict';

const EventEmitter = require('events'),
    ConnectionDelegate = require('./ConnectionDelegate'),
    ResourceRepository = require('./Data/ResourceRepository'),
    Instruction = require('./Instruction'),
    DataSerializer = require('./Data/Serializer'),
    DataUnserializer = require('./Data/Unserializer'),
    Logger = require('./Logger'),
    { Readable } = require('stream')
;

/**
 * Handle a connection interacting with this process.
 */
class Connection extends EventEmitter
{
    /**
     * Constructor.
     *
     * @param  {net.Socket} socket
     * @param  {ConnectionDelegate} delegate
     */
    constructor(socket, delegate)
    {
        super();

        this.socket = this.configureSocket(socket);

        this.delegate = delegate;

        this.resources = new ResourceRepository;

        this.dataSerializer = new DataSerializer(this.resources);
        this.dataUnserializer = new DataUnserializer(this.resources);
    }

    /**
     * Configure the socket for communication.
     *
     * @param  {net.Socket} socket
     * @return {net.Socket}
     */
    configureSocket(socket)
    {
        socket.setEncoding('utf8');

        let buffer = '';
        socket.on('data', data => {
            this.emit('activity');

            buffer += data;
            if (buffer.endsWith("\0")) {
                buffer = buffer.slice(0, -1);
                this.handleSocketData(buffer);
                buffer = '';
            }
        });

        return socket;
    }

    /**
     * Handle data received on the socket.
     *
     * @param  {string} data
     */
    handleSocketData(data)
    {
        const instruction = new Instruction(JSON.parse(data), this.resources, this.dataUnserializer),
            {responseHandler, errorHandler} = this.createInstructionHandlers();

        this.delegate.handleInstruction(instruction, responseHandler, errorHandler);
    }

    /**
     * Generate response and errors handlers.
     *
     * @return {Object}
     */
    createInstructionHandlers()
    {
        let handlerHasBeenCalled = false;

        const handler = (serializingMethod, value) => {
            if (handlerHasBeenCalled) {
                throw new Error('You can call only once the response/error handler.');
            }

            handlerHasBeenCalled = true;

            this.writeToSocket(JSON.stringify({
                logs: Logger.logs(),
                value: this[serializingMethod](value),
            }));
        };

        return {
            responseHandler: handler.bind(this, 'serializeValue'),
            errorHandler: handler.bind(this, 'serializeError'),
        };
    }

    /**
     * Write a string to the socket
     *
     * @param  {string} str
     */
    writeToSocket(str)
    {
        const payload = Buffer.from(str);
        const payloadLength = Buffer.alloc(4);
        payloadLength.writeUInt32BE(payload.length);

        this.socket.write(payloadLength);

        const readable = Readable.from(payload);
        readable.pipe(this.socket, { end: false });
    }

    /**
     * Serialize a value to return to the client.
     *
     * @param  {*} value
     * @return {Object}
     */
    serializeValue(value)
    {
        return this.dataSerializer.serialize(value);
    }

    /**
     * Serialize an error to return to the client.
     *
     * @param  {Error} error
     * @return {Object}
     */
    serializeError(error)
    {
        return DataSerializer.serializeError(error);
    }
}

module.exports = Connection;
