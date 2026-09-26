import { Link as RouterLink } from "react-router-dom";
// material
import { experimentalStyled as styled } from "@material-ui/core/styles";
import { Card, Stack, Link, Container, Typography } from "@material-ui/core";
// layouts
import AuthLayout from "../layouts/AuthLayout";
// components
import Page from "../components/Page";
import { MHidden } from "../components/@material-extend";
import { LoginForm } from "../components/authentication/login";
import AuthSocial from "../components/authentication/AuthSocial";
import "./Login.css";
// ----------------------------------------------------------------------

const RootStyle = styled(Page)(({ theme }) => ({
  [theme.breakpoints.up("md")]: {
    display: "flex",
  },
}));

const SectionStyle = styled(Card)(({ theme }) => ({
  width: "100%",
  maxWidth: 464,
  display: "flex",
  flexDirection: "column",
  justifyContent: "center",
  margin: theme.spacing(2, 0, 2, 2),
}));

const ContentStyle = styled("div")(({ theme }) => ({
  maxWidth: 480,
  margin: "auto",
  display: "flex",
  minHeight: "100vh",
  flexDirection: "column",
  justifyContent: "center",
  padding: theme.spacing(12, 0),
  width: "100vh",
}));

// ----------------------------------------------------------------------

export default function Login() {
  return (
    <RootStyle title="Login ">
      <ContentStyle className="p-5">
        <Stack sx={{ mb: 5 }}>
          <Typography variant="h4" gutterBottom >
            CONNECTEZ-VOUS
          </Typography>
        </Stack>

        <LoginForm />
      </ContentStyle>

      <MHidden width="mdDown">
        <Container
          minWidth="md"
          style={{
            marginTop: "-90px",
            marginRight: "-5px",
            backgroundImage: "url(/images/login-shell.jpg)",
            backgroundSize: "cover",
            backgroundPosition: "75% center",
            backgroundRepeat: "no-repeat",
          }}
        ></Container>
      </MHidden>
    </RootStyle>
  );
}
