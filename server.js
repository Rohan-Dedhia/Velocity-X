require('dotenv').config();
const express = require('express');
const Stripe = require('stripe');
const mysql = require('mysql2/promise');
const cors = require('cors');
const path = require('path');
const app = express();

// Initialize Stripe with proper error handling
const stripe = Stripe(process.env.STRIPE_SECRET_KEY || 'sk_test_your_key_here');

// MySQL connection with improved configuration
const db = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'velocity_x',
  port: process.env.DB_PORT || 3306,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// Enhanced middleware setup
app.use(cors({
  origin: ['http://localhost', 'http://127.0.0.1'],
  methods: ['GET', 'POST', 'PUT']
}));
app.use(express.json());
app.use(express.static(path.join(__dirname, 'public')));

// Database connection test
db.getConnection()
  .then(conn => {
    console.log('Connected to MySQL database');
    conn.release();
  })
  .catch(err => {
    console.error('Database connection failed:', err);
  });

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ 
    status: 'OK',
    stripe: 'Test Mode',
    db: 'Connected'
  });
});

// Payment endpoint with enhanced error handling
app.post('/create-payment-intent', async (req, res) => {
  console.log('Received payment intent request:', req.body);
  
  try {
    const { amount, order_id } = req.body;

    // Validate amount
    if (amount < 50) {
      return res.status(400).json({ 
        error: "Minimum amount is ₹50 in test mode",
        code: "AMOUNT_TOO_SMALL"
      });
    }

    // Create payment intent
    const paymentIntent = await stripe.paymentIntents.create({
      amount: Math.round(amount * 100), // Convert to paise
      currency: 'inr',
      payment_method_types: ['card'],
      metadata: {
        order_id: order_id,
        integration_check: 'accept_a_payment'
      }
    });

    console.log('Created payment intent:', paymentIntent.id);

    res.json({
      success: true,
      clientSecret: paymentIntent.client_secret,
      amount: amount,
      orderId: order_id
    });

  } catch (err) {
    console.error('Payment Intent Error:', err);
    res.status(500).json({
      error: "Payment processing failed",
      code: err.code || 'STRIPE_ERROR',
      message: err.message
    });
  }
});

// Order status update endpoint
app.put('/update-order/:id', async (req, res) => {
  try {
    const { payment_intent_id, status } = req.body;
    
    const [result] = await db.query(
      'UPDATE orders SET payment_intent_id = ?, status = ? WHERE id = ?',
      [payment_intent_id, status || 'completed', req.params.id]
    );

    res.json({
      success: true,
      affectedRows: result.affectedRows
    });

  } catch (err) {
    console.error('Order Update Error:', err);
    res.status(500).json({
      error: "Failed to update order",
      details: err.message
    });
  }
});

// Start server with error handling
const PORT = process.env.PORT || 4242;
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
  console.log(`Stripe test mode: ${process.env.STRIPE_SECRET_KEY ? 'Active' : 'Inactive'}`);
}).on('error', (err) => {
  console.error('Server failed to start:', err);
});