const dashboardChartConfig = window.dashboardTransactionChart || {
  labels: [],
  data: [],
};

const ctx = document.getElementById("transactionChart");

if (ctx) {
  new Chart(ctx, {
    type: "line",
    data: {
      labels: dashboardChartConfig.labels,
      datasets: [
        {
          label: "Transaction Volume",
          data: dashboardChartConfig.data,
          borderColor: "#0e6332",
          backgroundColor: "rgba(14, 99, 50, 0.12)",
          borderWidth: 3,
          tension: 0.42,
          fill: true,
          pointRadius: 5,
          pointHoverRadius: 7,
          pointBackgroundColor: "#d9a928",
          pointBorderColor: "#0e6332",
          pointBorderWidth: 2,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: {
            color: "#0e6332",
            font: {
              weight: "700",
            },
          },
        },
      },
      scales: {
        x: {
          ticks: {
            color: "#66756c",
          },
          grid: {
            color: "rgba(15, 23, 42, 0.06)",
          },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: "#66756c",
          },
          grid: {
            color: "rgba(15, 23, 42, 0.06)",
          },
        },
      },
    },
  });
}
