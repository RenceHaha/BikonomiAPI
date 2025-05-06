from flask import Flask, jsonify
import pandas as pd
from prophet import Prophet
from sqlalchemy import create_engine
import matplotlib.pyplot as plt
import json
import os
from datetime import datetime

app = Flask(__name__)

# Database connection details
user = 'root'
password = ''
host = 'localhost'  
port = '3306'
database = 'bikonomi_2'

# Create connection
engine = create_engine(f'mysql+pymysql://{user}:{password}@{host}:{port}/{database}')

def get_forecast_data():
    # Query to fetch the revenue data
    query = "SELECT date, SUM(amount_paid) as revenue FROM payment_tbl GROUP by DATE(date)"
    df = pd.read_sql(query, engine)
    
    # Ensure datetime format
    df['date'] = pd.to_datetime(df['date'])
    df = df.sort_values('date')
    
    # Prepare the data for Prophet
    ts = df[['date', 'revenue']].rename(columns={'date': 'ds', 'revenue': 'y'})
    
    # Initialize and fit the Prophet model
    model = Prophet()
    model.fit(ts)
    
    # Create future dataframe and make the forecast
    future = model.make_future_dataframe(periods=10)
    forecast = model.predict(future)
    
    # Get only future predictions
    last_date = datetime.now().date()
    future_forecast = forecast[forecast['ds'].dt.date > last_date]
    
    return future_forecast

def format_predictions(forecast, historical_data):
    """Format the predictions and historical data into JSON structure."""
    if forecast.empty:
        return {"error": "No predictions available"}
    
    # Format historical data
    historical = []
    for _, row in historical_data.iterrows():
        historical_point = {
            "date": row['ds'].strftime('%Y-%m-%d'),
            "actual_revenue": round(float(row['y']), 2)
        }
        historical.append(historical_point)
    
    # Format predictions
    predictions = []
    for _, row in forecast.iterrows():
        prediction = {
            "date": row['ds'].strftime('%Y-%m-%d'),
            "predicted_revenue": round(float(row['yhat']), 2),
            "lower_bound": round(float(row['yhat_lower']), 2),
            "upper_bound": round(float(row['yhat_upper']), 2)
        }
        predictions.append(prediction)
    
    return {
        "historical_data": historical,
        "predictions": predictions
    }

def save_predictions_to_file():
    """Save predictions and historical data to a JSON file."""
    try:
        # Get historical data
        query = "SELECT date, SUM(amount_paid) as revenue FROM payment_tbl GROUP by DATE(date)"
        df = pd.read_sql(query, engine)
        df['date'] = pd.to_datetime(df['date'])
        df = df.sort_values('date')
        ts = df[['date', 'revenue']].rename(columns={'date': 'ds', 'revenue': 'y'})
        
        # Get forecast
        forecast = get_forecast_data()
        
        # Combine both in the result
        result = format_predictions(forecast, ts)
        
        output_path = os.path.join(os.path.dirname(__file__), 'revenue_predictions.json')
        with open(output_path, 'w') as f:
            json.dump(result, f, indent=2)
        
        print(f"Predictions and historical data saved to {output_path}")
        return result
    except Exception as e:
        print(f"Error saving predictions: {e}")
        return {"error": str(e)}

@app.route('/forecast', methods=['GET'])
def get_forecast():
    forecast = get_forecast_data()
    return jsonify(format_predictions(forecast))

if __name__ == '__main__':
    # Save predictions to file
    predictions = save_predictions_to_file()
    print(json.dumps(predictions, indent=2))
    
    # Uncomment to run Flask API server
    # app.run(debug=True)
