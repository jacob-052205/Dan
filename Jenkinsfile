pipeline {
    agent any

    stages {
        stage('Checkout Code') {
            steps {
                echo 'Cloning repository from GitHub...'
                checkout scm
            }
        }

        stage('Deploy with Docker Compose') {
            steps {
                echo 'Stopping old containers...'
                bat 'docker-compose down'
                echo 'Building and starting new containers...'
                bat 'docker-compose up -d --build'
            }
        }
    }

    post {
        success {
            echo 'Pipeline executed successfully!'
            echo 'App running at http://localhost:8081'
        }
        failure {
            echo 'Pipeline failed. Check the logs.'
        }
    }
}