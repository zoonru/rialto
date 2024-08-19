"use strict";

import _ from "lodash";

const STANDARD_STREAMS = [process.stdout, process.stderr];

export default class StandardStreamsInterceptor {
    /**
     * Standard stream interceptor.
     *
     * @callback standardStreamInterceptor
     * @param  {string} message
     */

    /**
     * Start intercepting data written on the standard streams.
     *
     * @param  {standardStreamInterceptor} interceptor
     */
    static startInterceptingStrings(interceptor) {
        STANDARD_STREAMS.forEach((stream) => {
            this.standardStreamWriters.set(stream, stream.write);

            stream.write = (chunk, encoding, callback) => {
                if (_.isString(chunk)) {
                    interceptor(chunk);

                    if (_.isFunction(callback)) {
                        callback();
                    }

                    return true;
                }

                return stream.write(chunk, encoding, callback);
            };
        });
    }

    /**
     * Stop intercepting data written on the standard streams.
     */
    static stopInterceptingStrings() {
        STANDARD_STREAMS.forEach((stream) => {
            stream.write = this.standardStreamWriters.get(stream);
            this.standardStreamWriters.delete(stream);
        });
    }
}

StandardStreamsInterceptor.standardStreamWriters = new Map();
