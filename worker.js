export async function onRequest(context) {
    const { request, env } = context;
    const url = new URL(request.url);
    const path = url.searchParams.get('path');
    const method = request.method;

    const corsHeaders = {
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, DELETE, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type',
    };

    if (method === 'OPTIONS') {
        return new Response(null, { headers: corsHeaders });
    }

    try {
        const db = env.DB; // D1 binding, pastikan sudah diset di Pages

        if (method === 'GET') {
            if (path === 'docs') {
                const { results } = await db.prepare('SELECT data FROM docs ORDER BY createdAt DESC').all();
                const docs = results.map(row => JSON.parse(row.data));
                return Response.json(docs, { headers: corsHeaders });
            }
            if (path === 'pegawai') {
                const { results } = await db.prepare('SELECT data FROM pegawai ORDER BY createdAt DESC').all();
                const list = results.map(row => JSON.parse(row.data));
                return Response.json(list, { headers: corsHeaders });
            }
            if (path === 'kegiatan') {
                const { results } = await db.prepare('SELECT data FROM kegiatan ORDER BY createdAt DESC').all();
                const list = results.map(row => JSON.parse(row.data));
                return Response.json(list, { headers: corsHeaders });
            }
            return new Response('Not found', { status: 404, headers: corsHeaders });
        }

        if (method === 'POST') {
            const data = await request.json();
            const now = new Date().toISOString();

            if (path === 'docs') {
                const id = data.id || `doc_${Date.now()}`;
                await db.prepare(
                    'INSERT OR REPLACE INTO docs (id, data, createdAt, updatedAt) VALUES (?, ?, ?, ?)'
                ).bind(id, JSON.stringify(data), now, now).run();
                return Response.json({ success: true, id }, { headers: corsHeaders });
            }
            if (path === 'pegawai') {
                const id = data.id || `peg_${Date.now()}`;
                await db.prepare(
                    'INSERT OR REPLACE INTO pegawai (id, data, createdAt) VALUES (?, ?, ?)'
                ).bind(id, JSON.stringify(data), now).run();
                return Response.json({ success: true, id }, { headers: corsHeaders });
            }
            if (path === 'kegiatan') {
                const id = data.id || `keg_${Date.now()}`;
                await db.prepare(
                    'INSERT OR REPLACE INTO kegiatan (id, data, createdAt) VALUES (?, ?, ?)'
                ).bind(id, JSON.stringify(data), now).run();
                return Response.json({ success: true, id }, { headers: corsHeaders });
            }
            return new Response('Invalid path', { status: 400, headers: corsHeaders });
        }

        if (method === 'DELETE') {
            const id = url.searchParams.get('id');
            if (!id) return new Response('Missing id', { status: 400, headers: corsHeaders });
            let table;
            if (path === 'docs') table = 'docs';
            else if (path === 'pegawai') table = 'pegawai';
            else if (path === 'kegiatan') table = 'kegiatan';
            else return new Response('Invalid path', { status: 400, headers: corsHeaders });
            await db.prepare(`DELETE FROM ${table} WHERE id = ?`).bind(id).run();
            return Response.json({ success: true }, { headers: corsHeaders });
        }

        return new Response('Method not allowed', { status: 405, headers: corsHeaders });
    } catch (error) {
        return new Response(JSON.stringify({ error: error.message }), {
            status: 500,
            headers: { ...corsHeaders, 'Content-Type': 'application/json' }
        });
    }
}