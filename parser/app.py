from flask import Flask, request, jsonify
import subprocess
import json

app = Flask(__name__)

@app.route('/run-script', methods=['POST'])
def run_script():
    try:
        # Получаем JSON-данные из POST-запроса
        input_data = request.get_json()

        # Преобразуем данные в строку JSON
        json_input = json.dumps(input_data)

        # Запускаем Python-скрипт и передаем данные
        result = subprocess.run(
            ['python3', 'parser.py', json_input],
            capture_output=True,
            text=True
        )

        # Проверяем, завершился ли скрипт с ошибкой
        if result.returncode != 0:
            return jsonify({'error': result.stderr}), 500

        # Возвращаем результат выполнения скрипта
        return jsonify({'output': result.stdout})

    except Exception as e:
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    app.run(host='194.58.121.90', port=5001)