pipeline {
    agent any

    stages {
        stage("Checkout Code") {
            steps {
                echo "Cloning repository from GitHub..."
                checkout scm
            }
        }

        stage("Build Docker Image") {
            steps {
                echo "Building Docker image..."
                sh "docker build -t YOUR-DOCKER-USERNAME/dan-app:$BUILD_NUMBER ."
            }
        }

        stage("Push Docker Image") {
            steps {
                echo "Pushing Docker image to Docker Hub..."
                withDockerRegistry([credentialsId: "docker-hub-creds", url: ""]) {
                    sh "docker push YOUR-DOCKER-USERNAME/dan-app:$BUILD_NUMBER"
                }
            }
        }

        stage("Deploy Container") {
            steps {
                echo "Deploying container..."
                sh "docker stop dan-app || true"
                sh "docker rm dan-app || true"
                sh "docker run -d -p 8080:80 --name dan-app YOUR-DOCKER-USERNAME/dan-app:$BUILD_NUMBER"
            }
        }
    }

    post {
        success {
            echo "Pipeline executed successfully!"
        }
        failure {
            echo "Pipeline failed. Check the logs."
        }
    }
}
