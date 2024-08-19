"use strict";

import DataSerializer from "./Data/Serializer.mjs";
import Logger from "./Logger.mjs";
import ConsoleInterceptor from "./NodeInterceptors/ConsoleInterceptor.mjs";
import Server from "./Server.mjs";

// Throw unhandled rejections
process.on("unhandledRejection", (error) => {
    throw error;
});

// Output the exceptions in JSON format
process.on("uncaughtException", (error) => {
    process.stderr.write(JSON.stringify(DataSerializer.serializeError(error)));
    process.exit(1);
});

// Retrieve the options
let options = process.argv.slice(2)[1];
options = options !== undefined ? JSON.parse(options) : {};

// Intercept Node logs
if (options.log_node_console === true) {
    ConsoleInterceptor.startInterceptingLogs((type, originalMessage) => {
        const level = ConsoleInterceptor.getLevelFromType(type);
        const message = ConsoleInterceptor.formatMessage(originalMessage);

        Logger.log("Node", level, message);
    });
}

// Instanciate the custom connection delegate
const connectionDelegateClass = (await import(`file://${process.argv[2]}`)).default;
const connectionDelegate = new connectionDelegateClass(options);

// Start the server with the custom connection delegate
const server = new Server(connectionDelegate, options);

// Write the server port to the process output
server.started.then(() => server.writePortToOutput());
