#!/bin/bash

echo "🧪 Laravel Event Platform - Test Performance Comparison"
echo "======================================================="
echo ""

echo "📊 Running tests sequentially (default)..."
echo "-------------------------------------------"
time ./vendor/bin/pest

echo ""
echo "🚀 Running tests in parallel..."
echo "-------------------------------"
time ./vendor/bin/pest --parallel

echo ""
echo "✅ Performance comparison complete!"
echo "💡 Tip: Use './vendor/bin/pest --parallel' for faster test execution"
