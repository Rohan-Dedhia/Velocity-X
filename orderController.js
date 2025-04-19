// Endpoint to fetch order items
app.get('/get-order-items', (req, res) => {
    const orderId = req.query.orderId;

    const query = `
        SELECT id, item_name
        FROM order_items
        WHERE order_id = ?
    `;
    const values = [orderId];

    db.query(query, values, (err, results) => {
        if (err) {
            console.error('Error fetching order items:', err);
            return res.status(500).json({ error: 'Failed to fetch order items' });
        }

        res.json(results);
    });
});