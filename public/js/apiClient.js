export class RpcError extends Error {
    constructor(message, code, data) {
        super(message);
        this.name = 'RpcError';
        this.code = code;
        this.data = data;
    }
}

export async function rpcCall(method, params = {}) {
    const id = (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function')
        ? globalThis.crypto.randomUUID()
        : String(Date.now());
    const payload = {
        jsonrpc: '2.0',
        id,
        method,
        params
    };

    const response = await fetch('/api/rpc', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const data = await response.json();

    if (data && data.error) {
        throw new RpcError(data.error.message || 'RPC Error', data.error.code, data.error.data);
    }

    return data.result;
}
